<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Contracts\EInvoiceProviderInterface;
use Modules\Accounting\Models\Invoice;

/**
 * e-Fatura durum makinesi (PRD 3.14): not_sent → sent → accepted/rejected.
 * Yalnızca posted bir fatura gönderilebilir; muhasebe kaydını (dr/cr)
 * ETKİLEMEZ — bu, InvoiceService::post()'ta ayrıca ve önceden oluşur.
 */
class EInvoiceService
{
    public function __construct(private readonly EInvoiceProviderInterface $provider) {}

    public function send(Invoice $invoice): void
    {
        abort_unless($invoice->status === 'posted', 422, __('Only a posted invoice can be sent as an e-invoice.'));
        abort_unless($invoice->e_invoice_status === 'not_sent', 422, __('This invoice has already been sent.'));

        $uuid = $this->provider->send($invoice);

        $invoice->update(['e_invoice_status' => 'sent', 'gib_uuid' => $uuid]);
    }

    public function markAccepted(Invoice $invoice): void
    {
        abort_unless($invoice->e_invoice_status === 'sent', 422, __('Only a sent e-invoice can be marked as accepted.'));

        $invoice->update(['e_invoice_status' => 'accepted']);
    }

    public function markRejected(Invoice $invoice): void
    {
        abort_unless($invoice->e_invoice_status === 'sent', 422, __('Only a sent e-invoice can be marked as rejected.'));

        $invoice->update(['e_invoice_status' => 'rejected']);
    }
}
