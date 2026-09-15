<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Invoice;
use Modules\Purchase\Models\GoodsReceipt;
use Modules\Purchase\Models\PurchaseOrder;

/**
 * PO ↔ Mal Kabul ↔ Fatura üçlü tutar eşleştirmesi. Toleransı ondalık
 * duyarlıkla değil, absolut fark üzerinden değerlendirir (kuruş bazında).
 * Sadece kaynak `purchase_order` olan faturalar için çalışır; diğerleri
 * `not_applicable` ile işaretlenir ve UI'da rozet gösterilmez.
 */
class InvoiceMatchingService
{
    private const AMOUNT_TOLERANCE = '0.01';

    /**
     * Faturanın matching_status'unu hesaplar ve günceller.
     * Değer:
     *   not_applicable — kaynak PO değil
     *   matched        — PO amount = GR amount = Invoice subtotal (kuruş toleransıyla)
     *   mismatch       — herhangi bir taraf tutmuyor
     *   pending        — henüz mal kabul yok veya karşılaştırma yapılamıyor
     */
    public function evaluate(Invoice $invoice): string
    {
        if ($invoice->source_type !== 'purchase_order' || $invoice->source_id === null) {
            $invoice->update(['matching_status' => Invoice::MATCH_NOT_APPLICABLE]);

            return Invoice::MATCH_NOT_APPLICABLE;
        }

        $po = PurchaseOrder::withoutGlobalScopes()
            ->with('lines')
            ->where('tenant_id', $invoice->tenant_id)
            ->find($invoice->source_id);

        if (! $po) {
            $invoice->update(['matching_status' => Invoice::MATCH_PENDING]);

            return Invoice::MATCH_PENDING;
        }

        $poAmount = $this->orderAmount($po);
        $receivedAmount = $this->receivedAmount($po);
        $invoiceAmount = $invoice->subtotal();

        if (bccomp($receivedAmount, '0', 4) === 0) {
            $invoice->update(['matching_status' => Invoice::MATCH_PENDING]);

            return Invoice::MATCH_PENDING;
        }

        $poVsInvoice = $this->within($poAmount, $invoiceAmount);
        $grVsInvoice = $this->within($receivedAmount, $invoiceAmount);
        $poVsGr = $this->within($poAmount, $receivedAmount);

        $status = ($poVsInvoice && $grVsInvoice && $poVsGr)
            ? Invoice::MATCH_MATCHED
            : Invoice::MATCH_MISMATCH;

        $invoice->update(['matching_status' => $status]);

        return $status;
    }

    /**
     * @return array{po_amount:string, received_amount:string, invoice_amount:string}
     */
    public function breakdown(Invoice $invoice): array
    {
        if ($invoice->source_type !== 'purchase_order' || $invoice->source_id === null) {
            return ['po_amount' => '0.0000', 'received_amount' => '0.0000', 'invoice_amount' => $invoice->subtotal()];
        }

        $po = PurchaseOrder::withoutGlobalScopes()
            ->with('lines')
            ->where('tenant_id', $invoice->tenant_id)
            ->find($invoice->source_id);

        return [
            'po_amount' => $po ? $this->orderAmount($po) : '0.0000',
            'received_amount' => $po ? $this->receivedAmount($po) : '0.0000',
            'invoice_amount' => $invoice->subtotal(),
        ];
    }

    private function orderAmount(PurchaseOrder $po): string
    {
        return $po->lines->reduce(
            fn (string $carry, $line) => bcadd($carry, bcmul($line->qty, $line->unit_price, 4), 4),
            '0.0000',
        );
    }

    private function receivedAmount(PurchaseOrder $po): string
    {
        $sum = '0.0000';
        $unitPriceMap = $po->lines->pluck('unit_price', 'id');

        GoodsReceipt::withoutGlobalScopes()
            ->where('tenant_id', $po->tenant_id)
            ->where('purchase_order_id', $po->id)
            ->where('status', '!=', GoodsReceipt::STATUS_CANCELLED)
            ->with('lines')
            ->get()
            ->flatMap->lines
            ->each(function ($grLine) use (&$sum, $unitPriceMap): void {
                $unitPrice = $unitPriceMap[$grLine->purchase_order_line_id] ?? '0';
                $sum = bcadd($sum, bcmul($grLine->qty, (string) $unitPrice, 4), 4);
            });

        return $sum;
    }

    private function within(string $a, string $b): bool
    {
        $diff = bcsub($a, $b, 4);
        if (bccomp($diff, '0', 4) < 0) {
            $diff = bcmul($diff, '-1', 4);
        }

        return bccomp($diff, self::AMOUNT_TOLERANCE, 4) <= 0;
    }
}
