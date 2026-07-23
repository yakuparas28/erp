<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Services\ExchangeRateService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class ExchangeRateServiceTest extends TenantTestCase
{
    private function fakeTcmbXml(string $date): string
    {
        return <<<XML
        <?xml version="1.0" encoding="ISO-8859-9"?>
        <Tarih_Date Tarih="{$date}" Date="{$date}" Bulten_No="2026/1">
        <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD">
        <Unit>1</Unit>
        <Isim>ABD DOLARI</Isim>
        <CurrencyName>US DOLLAR</CurrencyName>
        <ForexBuying>32.500000</ForexBuying>
        <ForexSelling>32.600000</ForexSelling>
        <BanknoteBuying>32.400000</BanknoteBuying>
        <BanknoteSelling>32.700000</BanknoteSelling>
        </Currency>
        <Currency CrossOrder="1" Kod="EUR" CurrencyCode="EUR">
        <Unit>1</Unit>
        <Isim>EURO</Isim>
        <CurrencyName>EURO</CurrencyName>
        <ForexBuying>35.100000</ForexBuying>
        <ForexSelling>35.200000</ForexSelling>
        <BanknoteBuying>35.000000</BanknoteBuying>
        <BanknoteSelling>35.300000</BanknoteSelling>
        </Currency>
        </Tarih_Date>
        XML;
    }

    public function test_sync_from_tcmb_creates_exchange_rates_for_tracked_currencies(): void
    {
        $tenant = Tenant::factory()->create();
        $usd = Currency::factory()->for($tenant)->create(['code' => 'USD']);
        $eur = Currency::factory()->for($tenant)->create(['code' => 'EUR']);

        Http::fake([
            'tcmb.gov.tr/*' => Http::response($this->fakeTcmbXml('23.07.2026'), 200),
        ]);

        app(ExchangeRateService::class)->syncFromTcmb($tenant->id, '2026-07-23');

        $usdRate = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('currency_id', $usd->id)
            ->whereDate('rate_date', '2026-07-23')
            ->firstOrFail();

        $this->assertSame('32.500000', $usdRate->buy_rate);
        $this->assertSame('32.600000', $usdRate->sell_rate);
        $this->assertSame('tcmb', $usdRate->source);

        $eurRate = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('currency_id', $eur->id)
            ->whereDate('rate_date', '2026-07-23')
            ->firstOrFail();

        $this->assertSame('35.100000', $eurRate->buy_rate);
        $this->assertSame('35.200000', $eurRate->sell_rate);
        $this->assertSame('tcmb', $eurRate->source);
    }

    public function test_lock_rate_for_returns_the_buy_rate_for_an_existing_date(): void
    {
        $tenant = Tenant::factory()->create();
        $usd = Currency::factory()->for($tenant)->create(['code' => 'USD']);

        ExchangeRate::factory()->for($tenant)->create([
            'currency_id' => $usd->id,
            'rate_date' => '2026-07-23',
            'buy_rate' => '32.500000',
            'sell_rate' => '32.600000',
            'source' => 'manual',
        ]);

        $rate = app(ExchangeRateService::class)->lockRateFor($tenant->id, $usd->id, '2026-07-23');

        $this->assertSame('32.500000', $rate);
    }

    public function test_lock_rate_for_aborts_with_422_when_no_rate_exists_for_the_date(): void
    {
        $tenant = Tenant::factory()->create();
        $usd = Currency::factory()->for($tenant)->create(['code' => 'USD']);

        try {
            app(ExchangeRateService::class)->lockRateFor($tenant->id, $usd->id, '2026-07-23');
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_record_manual_rate_creates_a_rate_with_manual_source(): void
    {
        $tenant = Tenant::factory()->create();
        $usd = Currency::factory()->for($tenant)->create(['code' => 'USD']);

        $rate = app(ExchangeRateService::class)->recordManualRate(
            $tenant->id,
            $usd->id,
            '2026-07-23',
            '32.500000',
            '32.600000',
        );

        $this->assertSame('manual', $rate->source);
        $this->assertSame('32.500000', $rate->buy_rate);
        $this->assertSame(1, ExchangeRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_record_manual_rate_updates_the_existing_rate_instead_of_duplicating_it(): void
    {
        $tenant = Tenant::factory()->create();
        $usd = Currency::factory()->for($tenant)->create(['code' => 'USD']);

        app(ExchangeRateService::class)->recordManualRate($tenant->id, $usd->id, '2026-07-23', '32.500000', '32.600000');
        app(ExchangeRateService::class)->recordManualRate($tenant->id, $usd->id, '2026-07-23', '33.000000', '33.100000');

        $this->assertSame(1, ExchangeRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $rate = ExchangeRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('33.000000', $rate->buy_rate);
        $this->assertSame('33.100000', $rate->sell_rate);
    }

    public function test_sync_exchange_rates_command_syncs_all_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $usdA = Currency::factory()->for($tenantA)->create(['code' => 'USD']);
        $usdB = Currency::factory()->for($tenantB)->create(['code' => 'USD']);

        Http::fake([
            'tcmb.gov.tr/*' => Http::response($this->fakeTcmbXml(now()->format('d.m.Y')), 200),
        ]);

        $this->artisan('accounting:sync-exchange-rates')->assertExitCode(0);

        $this->assertTrue(
            ExchangeRate::withoutGlobalScopes()
                ->where('tenant_id', $tenantA->id)
                ->where('currency_id', $usdA->id)
                ->whereDate('rate_date', now()->toDateString())
                ->exists(),
        );

        $this->assertTrue(
            ExchangeRate::withoutGlobalScopes()
                ->where('tenant_id', $tenantB->id)
                ->where('currency_id', $usdB->id)
                ->whereDate('rate_date', now()->toDateString())
                ->exists(),
        );
    }
}
