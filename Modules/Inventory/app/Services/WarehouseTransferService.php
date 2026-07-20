<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\WarehouseTransfer;

/**
 * Depolar arası transfer (PRD 3.4): onayda her satır için kaynakta
 * negatif + hedefte pozitif iki bağlı stock_moves TEK transaction'da
 * üretilir; herhangi bir satır başarısızsa tamamı geri alınır.
 */
class WarehouseTransferService
{
    public function __construct(private readonly StockMoveService $stockMoves) {}

    public function complete(WarehouseTransfer $transfer): void
    {
        abort_unless(in_array($transfer->status, ['draft', 'in_transit'], true), 422, __('This transfer is already finalized.'));
        abort_if($transfer->moves()->exists(), 422, __('This transfer has already produced stock moves.'));

        DB::transaction(function () use ($transfer): void {
            foreach ($transfer->lines()->get() as $line) {
                $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
                $uom = Uom::withoutGlobalScopes()->findOrFail($line->uom_id);

                // Kaynakta çıkış (−)
                $this->stockMoves->move(
                    tenantId: $transfer->tenant_id,
                    product: $product,
                    fromLocationId: $transfer->from_location_id,
                    toLocationId: $transfer->to_location_id,
                    qty: bcmul($line->qty, '-1', 4),
                    uom: $uom,
                    referenceType: 'warehouse_transfer',
                    referenceId: $transfer->id,
                    lotId: $line->lot_id,
                );

                // Hedefte giriş (+)
                $this->stockMoves->move(
                    tenantId: $transfer->tenant_id,
                    product: $product,
                    fromLocationId: $transfer->from_location_id,
                    toLocationId: $transfer->to_location_id,
                    qty: $line->qty,
                    uom: $uom,
                    referenceType: 'warehouse_transfer',
                    referenceId: $transfer->id,
                    lotId: $line->lot_id,
                );
            }

            $transfer->update(['status' => 'completed']);
        });
    }
}
