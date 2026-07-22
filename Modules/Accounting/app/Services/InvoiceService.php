<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Fatura oluşturma (PRD 3.10/3.11). 3 yönlü eşleştirme (PRD YENİ KURAL)
 * yalnızca type=purchase + source=PurchaseOrder + bill_control_policy=
 * received_qty iken uygulanır: faturalanan kümülatif miktar receivedQty()'yi
 * aşamaz. ordered_qty policy'sinde tavan purchase_order_lines.qty'dir
 * (teslimattan bağımsız — PRD: "PO onaylanır onaylanmaz fatura oluşturulabilir").
 */
class InvoiceService
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function post(Invoice $invoice, User $poster): void
    {
        abort_unless($invoice->status === 'draft', 422, __('Only a draft invoice can be posted.'));
        abort_unless($poster->can('post journal entries'), 403, __('You are not allowed to post journal entries.'));

        $this->journalEntries->postForInvoice($invoice);

        $invoice->update(['status' => 'posted']);
    }

    public function create(int $tenantId, int $partnerId, string $type, Model $source): Invoice
    {
        $invoice = new Invoice(['partner_id' => $partnerId, 'type' => $type, 'status' => 'draft']);
        $invoice->tenant_id = $tenantId;
        $invoice->source()->associate($source);
        $invoice->save();

        return $invoice;
    }

    public function addLine(Invoice $invoice, int $productId, string $qty, string $unitPrice, ?int $taxRateId): InvoiceLine
    {
        abort_unless($invoice->status === 'draft', 422, __('Lines can only be added to a draft invoice.'));

        if ($invoice->type === 'purchase' && $invoice->source instanceof PurchaseOrder) {
            $this->assertThreeWayMatch($invoice->source, $productId, $qty, $invoice);
        }

        $line = new InvoiceLine([
            'invoice_id' => $invoice->id,
            'product_id' => $productId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'tax_rate_id' => $taxRateId,
        ]);
        $line->tenant_id = $invoice->tenant_id;
        $line->save();

        return $line;
    }

    private function assertThreeWayMatch(PurchaseOrder $po, int $productId, string $newQty, Invoice $invoice): void
    {
        $poLine = PurchaseOrderLine::withoutGlobalScopes()
            ->where('purchase_order_id', $po->id)->where('product_id', $productId)->first();

        abort_if($poLine === null, 422, __('This product is not on the purchase order.'));

        $alreadyInvoiced = InvoiceLine::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->whereIn('invoice_id', Invoice::withoutGlobalScopes()
                ->where('source_type', 'purchase_order')->where('source_id', $po->id)->pluck('id'))
            ->sum('qty');

        $cumulative = bcadd((string) $alreadyInvoiced, $newQty, 4);

        $cap = $po->bill_control_policy === 'received_qty'
            ? $poLine->receivedQty()
            : $poLine->qty;

        abort_if(bccomp($cumulative, $cap, 4) > 0, 422, __('Invoiced quantity cannot exceed the allowed quantity for this purchase order line.'));
    }
}
