<?php

namespace Modules\Accounting\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Accounting\Listeners\CreateJournalEntryFromPurchaseReceipt;
use Modules\Accounting\Listeners\CreateJournalEntryFromSalesDelivery;
use Modules\Purchase\Events\PurchaseOrderLineReceived;
use Modules\Sales\Events\SalesOrderLineDelivered;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        PurchaseOrderLineReceived::class => [
            CreateJournalEntryFromPurchaseReceipt::class,
        ],
        SalesOrderLineDelivered::class => [
            CreateJournalEntryFromSalesDelivery::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
