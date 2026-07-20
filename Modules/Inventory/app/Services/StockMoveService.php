<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;

/**
 * Stok hareket motoru (PRD 3.1/3.4/4.2). Kurallar:
 * - Kayıt DAİMA referans birimde (çevrim giriş sınırında yapılır).
 * - İşaret: qty>0 → to_location'da artış, qty<0 → from_location'da azalış.
 * - counting_lock'lu lokasyona hareket 409 (kilit sahibi fiş hariç).
 * - service ürüne stok hareketi 422; lot takipli üründe lot zorunlu.
 * - Quant güncellemesi tek transaction'da lockForUpdate ile atomiktir.
 */
class StockMoveService
{
    public function __construct(private readonly UomConversionService $uomConversion) {}

    public function move(
        int $tenantId,
        Product $product,
        ?int $fromLocationId,
        ?int $toLocationId,
        string $qty,
        Uom $uom,
        string $referenceType,
        int $referenceId,
        ?int $lotId = null,
        ?int $bypassLockForAdjustmentId = null,
    ): StockMove {
        abort_if($product->product_type === 'service', 422, __('Service products cannot have stock movements.'));
        abort_if($product->track_by !== 'none' && $lotId === null, 422, __('This product requires lot/serial tracking.'));

        $referenceQty = $this->signedToReference($qty, $uom);

        abort_if(bccomp($referenceQty, '0', 4) === 0, 422, __('Quantity cannot be zero.'));

        $this->assertLocationsNotLocked($tenantId, [$fromLocationId, $toLocationId], $referenceType, $referenceId, $bypassLockForAdjustmentId);

        return DB::transaction(function () use ($tenantId, $product, $fromLocationId, $toLocationId, $referenceQty, $lotId, $referenceType, $referenceId): StockMove {
            $isInbound = bccomp($referenceQty, '0', 4) > 0;
            $affectedLocationId = $isInbound ? $toLocationId : $fromLocationId;

            abort_if($affectedLocationId === null, 422, __('The affected location is required.'));

            $quant = StockQuant::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('location_id', $affectedLocationId)
                ->when($lotId === null, fn ($q) => $q->whereNull('lot_id'), fn ($q) => $q->where('lot_id', $lotId))
                ->lockForUpdate()
                ->first();

            if ($quant === null) {
                $quant = new StockQuant([
                    'product_id' => $product->id,
                    'location_id' => $affectedLocationId,
                    'lot_id' => $lotId,
                    'qty' => '0',
                ]);
                $quant->tenant_id = $tenantId;
                $quant->save();
            }

            if (! $isInbound) {
                $available = bcsub($quant->qty, $quant->reserved_qty, 4);
                $outgoing = bcmul($referenceQty, '-1', 4);

                abort_if(bccomp($available, $outgoing, 4) < 0, 422, __('Insufficient stock at the source location.'));
            }

            $move = new StockMove([
                'product_id' => $product->id,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'uom_id' => $product->uom_id,
                'lot_id' => $lotId,
                'qty' => $referenceQty,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
            $move->tenant_id = $tenantId;
            $move->save();

            $isInbound
                ? $quant->increment('qty', $referenceQty)
                : $quant->decrement('qty', bcmul($referenceQty, '-1', 4));

            $this->refreshCurrentStock($tenantId, $product);

            return $move;
        });
    }

    /**
     * @param  array<int, int|null>  $locationIds
     */
    private function assertLocationsNotLocked(
        int $tenantId,
        array $locationIds,
        string $referenceType,
        int $referenceId,
        ?int $bypassLockForAdjustmentId,
    ): void {
        $ownsLock = $referenceType === 'inventory_adjustment' && $referenceId === $bypassLockForAdjustmentId;

        if ($ownsLock) {
            return;
        }

        $locked = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_filter($locationIds)))
            ->where('counting_lock', true)
            ->exists();

        abort_if($locked, 409, __('The location is locked by an ongoing stock count.'));
    }

    private function signedToReference(string $qty, Uom $uom): string
    {
        $isNegative = bccomp($qty, '0', 6) < 0;
        $absolute = $isNegative ? bcmul($qty, '-1', 6) : $qty;
        $converted = $this->uomConversion->toReference($uom, $absolute);

        return $isNegative ? bcmul($converted, '-1', 4) : $converted;
    }

    private function refreshCurrentStock(int $tenantId, Product $product): void
    {
        $total = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->sum('qty');

        Product::withoutGlobalScopes()->whereKey($product->id)->update(['current_stock' => $total]);
    }
}
