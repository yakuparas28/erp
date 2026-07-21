<?php

namespace Modules\Sales\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\KitExplosionService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Satış sipariş yaşam döngüsü (PRD 3.11): draft→quotation_sent→confirmed→
 * done/cancelled. Görev ayrılığı: oluşturan kullanıcı kendi SO'sunu
 * onaylayamaz. Onayda rezervasyon yalnızca track_by='none' + is_kit=false +
 * product_type≠'service' satırlarda uygulanır (bkz. plan Architecture notu).
 */
class SalesOrderService
{
    public function __construct(
        private readonly StockMoveService $stockMoves,
        private readonly CostingService $costing,
        private readonly KitExplosionService $kitExplosion,
    ) {}

    public function create(int $tenantId, int $partnerId, int $locationId, User $creator): SalesOrder
    {
        $so = new SalesOrder([
            'partner_id' => $partnerId,
            'location_id' => $locationId,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);
        $so->tenant_id = $tenantId;
        $so->save();

        return $so;
    }

    public function addLine(SalesOrder $so, int $productId, int $uomId, string $qty, string $unitPrice): SalesOrderLine
    {
        abort_unless($so->status === 'draft', 422, __('Lines can only be added to a draft sales order.'));

        $line = new SalesOrderLine([
            'sales_order_id' => $so->id,
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
        ]);
        $line->tenant_id = $so->tenant_id;
        $line->save();

        return $line;
    }

    public function sendQuotation(SalesOrder $so): void
    {
        abort_unless($so->status === 'draft', 422, __('Only draft sales orders can be sent as a quotation.'));

        $so->update(['status' => 'quotation_sent']);
    }

    public function confirm(SalesOrder $so, User $approver): void
    {
        abort_unless($so->status === 'quotation_sent', 422, __('Only a quotation-sent sales order can be confirmed.'));
        abort_if($so->created_by === $approver->id, 403, __('You cannot confirm a sales order you created.'));
        abort_unless($approver->can('confirm sales orders'), 403, __('You are not allowed to confirm sales orders.'));

        DB::transaction(function () use ($so): void {
            foreach ($so->lines as $line) {
                $this->reserveLine($so, $line);
            }

            $so->update(['status' => 'confirmed']);
        });
    }

    public function cancel(SalesOrder $so): void
    {
        abort_if(in_array($so->status, ['done', 'cancelled'], true), 422, __('This sales order is already finalized.'));

        DB::transaction(function () use ($so): void {
            if ($so->status === 'confirmed') {
                foreach ($so->lines as $line) {
                    $remaining = bcsub($line->qty, $line->delivered_qty, 4);

                    if (bccomp($remaining, '0', 4) > 0) {
                        $this->releaseReservation($so, $line, $remaining);
                    }
                }
            }

            $so->update(['status' => 'cancelled']);
        });
    }

    /**
     * Fiili teslimat (PRD 3.11): hizmet satırı hiçbir şey üretmez; kit
     * satırı bileşenlere patlar (kit'in kendisi asla move'a girmez);
     * normal satır tek bir çıkış hareketi + COGS üretir ve (rezerve
     * edilmişse) rezervi serbest bırakır. Tüm satırlar tam teslim
     * edilince SO 'done' durumuna geçer.
     */
    public function deliver(SalesOrderLine $line, string $qty): void
    {
        $so = $line->salesOrder;

        abort_unless($so->status === 'confirmed', 422, __('Only a confirmed sales order can be delivered.'));

        $remaining = bcsub($line->qty, $line->delivered_qty ?? '0', 4);
        abort_if(bccomp($qty, $remaining, 4) > 0, 422, __('Delivered quantity cannot exceed the remaining ordered quantity.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        DB::transaction(function () use ($so, $line, $qty, $product): void {
            if ($product->product_type === 'service') {
                $this->increaseDeliveredQty($so, $line, $qty);

                return;
            }

            if ($product->is_kit) {
                $moves = $this->kitExplosion->explode(
                    kit: $product,
                    kitQty: $qty,
                    fromLocationId: $so->location_id,
                    toLocationId: null,
                    referenceType: 'sales_order_line',
                    referenceId: $line->id,
                );

                foreach ($moves as $move) {
                    $moveProduct = Product::withoutGlobalScopes()->findOrFail($move->product_id);
                    $this->costing->consumeOutbound($moveProduct, $move, bcmul($move->qty, '-1', 4));
                }

                $this->increaseDeliveredQty($so, $line, $qty);

                return;
            }

            if ($this->isReservable($product)) {
                $this->releaseReservation($so, $line, $qty);
            }

            $move = $this->stockMoves->move(
                tenantId: $so->tenant_id,
                product: $product,
                fromLocationId: $so->location_id,
                toLocationId: null,
                qty: bcmul($qty, '-1', 4),
                uom: $line->uom,
                referenceType: 'sales_order_line',
                referenceId: $line->id,
            );

            $this->costing->consumeOutbound($product, $move, $qty);

            $this->increaseDeliveredQty($so, $line, $qty);
        });
    }

    private function increaseDeliveredQty(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $line->increment('delivered_qty', $qty);
        $this->markDoneIfFullyDelivered($so);
    }

    private function markDoneIfFullyDelivered(SalesOrder $so): void
    {
        $so->refresh();

        $fullyDelivered = $so->lines->every(fn (SalesOrderLine $line) => bccomp($line->fresh()->delivered_qty, $line->qty, 4) === 0);

        if ($fullyDelivered) {
            $so->update(['status' => 'done']);
        }
    }

    private function reserveLine(SalesOrder $so, SalesOrderLine $line): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        $quant = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->lockForUpdate()
            ->first();

        if ($quant === null) {
            $quant = new StockQuant([
                'product_id' => $product->id,
                'location_id' => $so->location_id,
                'lot_id' => null,
                'qty' => '0',
            ]);
            $quant->tenant_id = $so->tenant_id;
            $quant->save();
        }

        $newReserved = bcadd($quant->reserved_qty, $line->qty, 4);

        abort_if(bccomp($newReserved, $quant->qty, 4) > 0, 422, __('Insufficient available stock to reserve.'));

        $quant->update(['reserved_qty' => $newReserved]);
    }

    private function releaseReservation(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->decrement('reserved_qty', $qty);
    }

    private function isReservable(Product $product): bool
    {
        return $product->track_by === 'none' && ! $product->is_kit && $product->product_type !== 'service';
    }
}
