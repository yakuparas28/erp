<?php

namespace Modules\Accounting\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Accounting\Services\ExchangeRateService;

class SyncExchangeRates extends Command
{
    protected $signature = 'accounting:sync-exchange-rates';

    protected $description = 'Sync daily TCMB exchange rates for all tenants';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        foreach (Tenant::withoutGlobalScopes()->cursor() as $tenant) {
            $exchangeRates->syncFromTcmb($tenant->id);
        }

        return self::SUCCESS;
    }
}
