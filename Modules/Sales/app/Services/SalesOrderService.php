<?php

namespace Modules\Sales\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
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
