<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Services\AccountingDefaultsService;
use Tests\TenantTestCase;

class ExchangeRateScreensTest extends TenantTestCase
{
    public function test_exchange_rates_index_only_lists_non_functional_currencies_in_the_select(): void
    {
        app(AccountingDefaultsService::class)->provision($this->tenant);

        $response = $this->actingAs($this->tenantAdmin)->get('/app/accounting/exchange-rates');

        $response->assertOk();
        $response->assertSee('USD');
        $response->assertSee('EUR');
        $response->assertDontSee('TRY');
    }

    public function test_manual_exchange_rate_can_be_recorded(): void
    {
        $currency = Currency::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'USD']);

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/exchange-rates', [
            'currency_id' => $currency->id,
            'rate_date' => '2026-07-27',
            'buy_rate' => '33.100000',
            'sell_rate' => '33.200000',
        ])->assertRedirect(route('app.accounting.exchange-rates.index'));

        $this->assertDatabaseHas('exchange_rates', [
            'tenant_id' => $this->tenant->id,
            'currency_id' => $currency->id,
            'buy_rate' => '33.100000',
            'sell_rate' => '33.200000',
            'source' => 'manual',
        ]);
    }

    public function test_recording_the_same_currency_and_date_twice_updates_instead_of_duplicating(): void
    {
        $currency = Currency::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'USD']);

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/exchange-rates', [
            'currency_id' => $currency->id,
            'rate_date' => '2026-07-27',
            'buy_rate' => '33.100000',
            'sell_rate' => '33.200000',
        ]);

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/exchange-rates', [
            'currency_id' => $currency->id,
            'rate_date' => '2026-07-27',
            'buy_rate' => '33.500000',
            'sell_rate' => '33.600000',
        ]);

        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertDatabaseHas('exchange_rates', [
            'tenant_id' => $this->tenant->id,
            'currency_id' => $currency->id,
            'buy_rate' => '33.500000',
            'sell_rate' => '33.600000',
        ]);
    }

    public function test_user_without_permission_cannot_access_exchange_rates_page(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/accounting/exchange-rates')
            ->assertForbidden();
    }
}
