<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\SyncBatch;

/**
 * Offline sayım sepetinin toplu gönderimi (PRD 3.2–3.3). Idempotency
 * sync_batches unique(tenant_id, batch_uuid) constraint'iyle DB seviyesinde
 * garanti edilir; kontrol+yazma tek transaction'dadır. Barkod→ürün/birim
 * çözümlemesi sunucuda TEKRAR yapılır — istemcinin gönderdiği referans
 * miktara güvenilmez, yalnızca barkod + kendi biriminde ham miktar kabul
 * edilir.
 */
class SyncBulkPushService
{
    public function __construct(
        private readonly UomConversionService $uomConversion,
        private readonly InventoryAdjustmentService $adjustments,
    ) {}

    /**
     * @param  array<int, array{barcode: string, qty: string, lot_id?: int|null}>  $lines
     */
    public function push(int $tenantId, string $batchUuid, InventoryAdjustment $adjustment, array $lines): void
    {
        abort_unless($adjustment->status === 'counting', 422, __('This adjustment is not currently being counted.'));

        try {
            DB::transaction(function () use ($tenantId, $batchUuid, $adjustment, $lines): void {
                $batch = new SyncBatch(['batch_uuid' => $batchUuid]);
                $batch->tenant_id = $tenantId;
                $batch->save();

                foreach ($lines as $line) {
                    $barcode = ProductBarcode::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('barcode', $line['barcode'])
                        ->with('product', 'uom')
                        ->first();

                    abort_if($barcode === null, 422, __('Unknown barcode: :barcode', ['barcode' => $line['barcode']]));

                    $uom = $barcode->uom ?? $barcode->product->uom;
                    $referenceQty = $this->uomConversion->toReference($uom, $line['qty']);

                    $this->adjustments->addCount($adjustment, $barcode->product_id, $referenceQty, $line['lot_id'] ?? null);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Aynı batch_uuid daha önce işlendi — ağ kesintisi kaynaklı
            // tekrar gönderimde idempotent olarak sessizce başarı dönülür.
        }
    }
}
