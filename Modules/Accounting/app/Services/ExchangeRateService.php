<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Modules\Accounting\Contracts\TcmbClientInterface;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;

/**
 * Kur kilitleme (PRD 3.13 — belgenin oluşturulduğu tarihteki TCMB ALIŞ
 * kuru) + günlük TCMB senkronizasyonu + istisnai günler için manuel giriş.
 */
class ExchangeRateService
{
    public function __construct(private readonly TcmbClientInterface $client) {}

    public function lockRateFor(int $tenantId, int $currencyId, string $date): string
    {
        $rate = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('currency_id', $currencyId)
            ->where('rate_date', $this->normalizeRateDate($date))
            ->first();

        abort_if($rate === null, 422, __('No exchange rate is available for this currency on this date.'));

        return (string) $rate->buy_rate;
    }

    public function syncFromTcmb(int $tenantId, ?string $date = null): void
    {
        $date ??= now()->toDateString();
        $rates = $this->client->fetchRates($date);

        foreach ($rates as $code => $rate) {
            $currency = Currency::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('code', $code)->first();

            if ($currency === null) {
                continue;
            }

            ExchangeRate::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'currency_id' => $currency->id, 'rate_date' => $this->normalizeRateDate($date)],
                ['buy_rate' => $rate['buy'], 'sell_rate' => $rate['sell'], 'source' => 'tcmb'],
            );
        }
    }

    public function recordManualRate(int $tenantId, int $currencyId, string $date, string $buyRate, string $sellRate): ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'currency_id' => $currencyId, 'rate_date' => $this->normalizeRateDate($date)],
            ['buy_rate' => $buyRate, 'sell_rate' => $sellRate, 'source' => 'manual'],
        );
    }

    /**
     * `rate_date` is cast to `date`, but Eloquent's date cast still persists
     * values using the connection's full datetime format (e.g. "2026-07-23
     * 00:00:00"). Raw where()/updateOrCreate() search arrays bypass that
     * cast, so on loosely-typed columns (SQLite) a plain "Y-m-d" search
     * value silently fails to match — and on updateOrCreate can even
     * trigger a duplicate-row unique constraint violation. Normalizing the
     * search value to the same persisted format keeps lookups correct on
     * every database driver.
     */
    private function normalizeRateDate(string $date): string
    {
        return Carbon::parse($date)->startOfDay()->format('Y-m-d H:i:s');
    }
}
