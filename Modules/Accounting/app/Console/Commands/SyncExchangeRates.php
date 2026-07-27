<?php

namespace Modules\Accounting\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Services\ExchangeRateService;

class SyncExchangeRates extends Command
{
    protected $signature = 'accounting:sync-exchange-rates';

    protected $description = 'Sync daily TCMB exchange rates for all tenants';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        foreach (Tenant::withoutGlobalScopes()->cursor() as $tenant) {
            try {
                $exchangeRates->syncFromTcmb($tenant->id);
            } catch (\Throwable $e) {
                Log::warning('TCMB exchange rate sync failed for tenant.', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
