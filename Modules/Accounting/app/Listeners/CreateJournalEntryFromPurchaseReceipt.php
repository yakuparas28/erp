<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\JournalEntryService;
use Modules\Purchase\Events\PurchaseOrderLineReceived;

class CreateJournalEntryFromPurchaseReceipt
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function handle(PurchaseOrderLineReceived $event): void
    {
        $this->journalEntries->postForPurchaseReceipt($event->line, $event->move);
    }
}
