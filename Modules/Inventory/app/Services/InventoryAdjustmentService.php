<?php

namespace Modules\Inventory\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\InventoryAdjustmentLine;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;

/**
 * Kör sayım fişi döngüsü (PRD 3.1/3.4):
 * draft → counting (lokasyon kilitlenir) → pending_approval → approved/cancelled.
 * Sayımlar ADDITIVE birleşir; onayda fark move'ları TEK SEFER üretilir;
 * fişi açan kendi fişini onaylayamaz (görev ayrılığı).
 */
class InventoryAdjustmentService
{
    public function __construct(private readonly StockMoveService $stockMoves) {}

    public function open(int $tenantId, int $locationId, User $creator): InventoryAdjustment
    {
        $adjustment = new InventoryAdjustment([
            'location_id' => $locationId,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);
        $adjustment->tenant_id = $tenantId;
        $adjustment->save();

        return $adjustment;
    }

    public function startCounting(InventoryAdjustment $adjustment): void
    {
        abort_unless($adjustment->status === 'draft', 422, __('Only draft adjustments can start counting.'));

        DB::transaction(function () use ($adjustment): void {
            $location = Location::withoutGlobalScopes()
                ->where('tenant_id', $adjustment->tenant_id)
                ->whereKey($adjustment->location_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($location->counting_lock, 409, __('The location is locked by an ongoing stock count.'));

            $location->update(['counting_lock' => true]);
            $adjustment->update(['status' => 'counting']);
        });
    }

    /**
     * Additive birleştirme: aynı ürün+lot satırına EKLENİR (PRD 3.2).
     */
    public function addCount(InventoryAdjustment $adjustment, int $productId, string $qty, ?int $lotId): InventoryAdjustmentLine
    {
        abort_unless($adjustment->status === 'counting', 422, __('Counts can only be added while counting.'));

        return DB::transaction(function () use ($adjustment, $productId, $qty, $lotId): InventoryAdjustmentLine {
            $line = InventoryAdjustmentLine::withoutGlobalScopes()
                ->where('tenant_id', $adjustment->tenant_id)
                ->where('inventory_adjustment_id', $adjustment->id)
                ->where('product_id', $productId)
                ->when($lotId === null, fn ($q) => $q->whereNull('lot_id'), fn ($q) => $q->where('lot_id', $lotId))
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                $line = new InventoryAdjustmentLine([
                    'inventory_adjustment_id' => $adjustment->id,
                    'product_id' => $productId,
                    'lot_id' => $lotId,
                    'counted_qty' => '0',
                ]);
                $line->tenant_id = $adjustment->tenant_id;
                $line->save();
            }

            $line->increment('counted_qty', $qty);

            return $line->fresh();
        });
    }

    public function submitForApproval(InventoryAdjustment $adjustment): void
    {
        abort_unless($adjustment->status === 'counting', 422, __('Only counting adjustments can be submitted.'));

        $adjustment->update(['status' => 'pending_approval']);
    }

    public function approve(InventoryAdjustment $adjustment, User $approver): void
    {
        abort_unless($adjustment->status === 'pending_approval', 422, __('Only pending adjustments can be approved.'));
        abort_if($adjustment->created_by === $approver->id, 403, __('You cannot approve an adjustment you created.'));

        setPermissionsTeamId($adjustment->tenant_id);
        abort_unless($approver->can('approve inventory adjustments'), 403, __('You are not allowed to approve adjustments.'));

        DB::transaction(function () use ($adjustment, $approver): void {
            // Immutable ledger: aynı fiş için ikinci kez move üretilmez.
            $alreadyPosted = $adjustment->moves()->exists();

            if (! $alreadyPosted) {
                foreach ($adjustment->lines()->get() as $line) {
                    $theoretical = (string) StockQuant::withoutGlobalScopes()
                        ->where('tenant_id', $adjustment->tenant_id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $adjustment->location_id)
                        ->when($line->lot_id === null, fn ($q) => $q->whereNull('lot_id'), fn ($q) => $q->where('lot_id', $line->lot_id))
                        ->sum('qty');

                    $difference = bcsub($line->counted_qty, $theoretical, 4);

                    $line->update(['theoretical_qty' => $theoretical]);

                    if (bccomp($difference, '0', 4) === 0) {
                        continue;
                    }

                    $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
                    $isPositive = bccomp($difference, '0', 4) > 0;

                    $this->stockMoves->move(
                        tenantId: $adjustment->tenant_id,
                        product: $product,
                        fromLocationId: $isPositive ? null : $adjustment->location_id,
                        toLocationId: $isPositive ? $adjustment->location_id : null,
                        qty: $difference,
                        uom: $product->uom,
                        referenceType: 'inventory_adjustment',
                        referenceId: $adjustment->id,
                        lotId: $line->lot_id,
                        bypassLockForAdjustmentId: $adjustment->id,
                    );
                }
            }

            $adjustment->update(['status' => 'approved', 'approved_by' => $approver->id]);
            $this->releaseLock($adjustment);
        });
    }

    public function cancel(InventoryAdjustment $adjustment): void
    {
        abort_if(in_array($adjustment->status, ['approved', 'cancelled'], true), 422, __('This adjustment is already finalized.'));

        DB::transaction(function () use ($adjustment): void {
            $adjustment->update(['status' => 'cancelled']);
            $this->releaseLock($adjustment);
        });
    }

    private function releaseLock(InventoryAdjustment $adjustment): void
    {
        Location::withoutGlobalScopes()
            ->where('tenant_id', $adjustment->tenant_id)
            ->whereKey($adjustment->location_id)
            ->update(['counting_lock' => false]);
    }
}
