<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\JournalEntryService;
use Modules\Sales\Events\SalesOrderLineDelivered;

class CreateJournalEntryFromSalesDelivery
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function handle(SalesOrderLineDelivered $event): void
    {
        $this->journalEntries->postForSalesDelivery($event->line, $event->move, $event->cogsAmount);
    }
}
