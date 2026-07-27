<?php

namespace Modules\Accounting\Services\EInvoice;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Accounting\Contracts\EInvoiceProviderInterface;
use Modules\Accounting\Models\Invoice;

class NullEInvoiceProvider implements EInvoiceProviderInterface
{
    public function send(Invoice $invoice): string
    {
        Log::info('e-Invoice send (null provider)', ['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

        return (string) Str::uuid();
    }
}
