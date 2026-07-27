<?php

namespace Modules\Accounting\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Accounting\Console\Commands\SyncExchangeRates;
use Modules\Accounting\Contracts\EInvoiceProviderInterface;
use Modules\Accounting\Contracts\TcmbClientInterface;
use Modules\Accounting\Services\EInvoice\NullEInvoiceProvider;
use Modules\Accounting\Services\Tcmb\HttpTcmbClient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AccountingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Accounting';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'accounting';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncExchangeRates::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(TcmbClientInterface::class, HttpTcmbClient::class);
        $this->app->bind(EInvoiceProviderInterface::class, NullEInvoiceProvider::class);
    }

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('accounting:sync-exchange-rates')->dailyAt('09:00');
    }
}
