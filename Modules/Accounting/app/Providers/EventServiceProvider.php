<?php

namespace Modules\Accounting\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Accounting\Listeners\CreateJournalEntryFromPurchaseReceipt;
use Modules\Purchase\Events\PurchaseOrderLineReceived;

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
