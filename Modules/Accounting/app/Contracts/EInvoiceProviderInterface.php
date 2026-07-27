<?php

namespace Modules\Accounting\Contracts;

use Modules\Accounting\Models\Invoice;

/**
 * e-Fatura/e-Arşiv sağlayıcı sözleşmesi (Strategy — CostingStrategyInterface
 * ile aynı desende). Gerçek entegratör (Sovos/Uyumsoft/Foriba) adaptörü
 * ayrı bir iş; bu fazda yalnızca bir null/log sağlayıcı sağlanır.
 */
interface EInvoiceProviderInterface
{
    public function send(Invoice $invoice): string; // GİB UUID döner
}
