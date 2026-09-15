<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\InvoiceMatchingService;
use Modules\Purchase\Models\GoodsReceipt;
use Modules\Purchase\Models\GoodsReceiptLine;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Mal Kabul Fişi. PurchaseOrderService::receive()'in yanı sıra çağrılır;
 * stok hareketi hâlâ receive()'da, bu servis belge kaydı üretir.
 * 3-way matching için data source (Faz 3).
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly InvoiceMatchingService $matching,
    ) {}

    public function recordReceipt(PurchaseOrderLine $line, string $qty, int $locationId, array $meta = []): GoodsReceipt
    {
        return DB::transaction(function () use ($line, $qty, $locationId, $meta) {
            $po = $line->purchaseOrder;
            $receipt = new GoodsReceipt([
                'purchase_order_id' => $po->id,
                'receipt_no' => $this->nextReceiptNo($po->tenant_id),
                'receipt_date' => $meta['receipt_date'] ?? now()->toDateString(),
                'warehouse_location_id' => $locationId,
                'waybill_no' => $meta['waybill_no'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'status' => GoodsReceipt::STATUS_RECEIVED,
                'created_by' => auth()->id(),
            ]);
            $receipt->tenant_id = $po->tenant_id;
            $receipt->save();
            $receiptLine = new GoodsReceiptLine([
                'goods_receipt_id' => $receipt->id,
                'purchase_order_line_id' => $line->id,
                'product_id' => $line->product_id,
                'uom_id' => $line->uom_id,
                'qty' => $qty,
            ]);
            $receiptLine->tenant_id = $po->tenant_id;
            $receiptLine->save();

            $this->reevaluateInvoicesFor($po->id, $po->tenant_id);

            return $receipt;
        });
    }

    /**
     * Bir PO'ya bağlı tüm faturaların matching_status'unu yeniden hesaplar
     * (yeni bir mal kabul geldiğinde ya da iptal edildiğinde).
     */
    public function reevaluateInvoicesFor(int $purchaseOrderId, int $tenantId): void
    {
        Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'purchase_order')
            ->where('source_id', $purchaseOrderId)
            ->get()
            ->each(fn (Invoice $inv) => $this->matching->evaluate($inv));
    }

    public function totalReceivedForLine(int $lineId): string
    {
        return (string) (GoodsReceiptLine::where('purchase_order_line_id', $lineId)
            ->whereHas('goodsReceipt', fn ($q) => $q->where('status', '!=', GoodsReceipt::STATUS_CANCELLED))
            ->sum('qty') ?? 0);
    }

    private function nextReceiptNo(int $tenantId): string
    {
        $year = now()->format('Y');
        $last = GoodsReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('receipt_no', 'like', "MKF-{$year}-%")
            ->orderByDesc('id')
            ->first();
        $next = 1;
        if ($last !== null) {
            $parts = explode('-', $last->receipt_no);
            $next = ((int) end($parts)) + 1;
        }

        return sprintf('MKF-%s-%06d', $year, $next);
    }
}
