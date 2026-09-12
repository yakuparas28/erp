<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Purchase\Models\PurchaseOrder;
use Tests\TenantTestCase;

class CurrencyScreensTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    public function test_currencies_index_lists_provisioned_currencies_with_symbols(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/accounting/currencies')
            ->assertOk()
            ->assertSee('TRY')
            ->assertSee('USD')
            ->assertSee('EUR')
            ->assertSee('₺')
            ->assertSee('$');
    }

    public function test_new_currency_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/accounting/currencies', [
            'code' => 'GBP',
            'name' => 'İngiliz Sterlini',
            'symbol' => '£',
            'position' => 'before',
            'rounding' => '0.01',
        ])->assertRedirect(route('app.accounting.currencies.index'));

        $this->assertDatabaseHas('currencies', [
            'tenant_id' => $this->tenant->id,
            'code' => 'GBP',
            'name' => 'İngiliz Sterlini',
            'symbol' => '£',
            'position' => 'before',
            'is_functional' => false,
            'active' => true,
        ]);
    }

    public function test_code_must_be_three_uppercase_letters(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/accounting/currencies', [
            'code' => 'gb',
            'name' => 'İngiliz Sterlini',
            'position' => 'before',
            'rounding' => '0.01',
        ])->assertSessionHasErrors('code');
    }

    public function test_rounding_must_be_greater_than_zero(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/accounting/currencies', [
            'code' => 'GBP',
            'name' => 'Pound',
            'position' => 'before',
            'rounding' => '0',
        ])->assertSessionHasErrors('rounding');
    }

    public function test_code_must_be_unique_within_the_tenant(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/accounting/currencies', [
            'code' => 'USD',
            'name' => 'Duplicate',
            'position' => 'before',
            'rounding' => '0.01',
        ])->assertSessionHasErrors('code');
    }

    public function test_currency_name_symbol_position_rounding_can_be_updated(): void
    {
        $currency = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->patch("/app/accounting/currencies/{$currency->id}", [
                'name' => 'US Dollar',
                'symbol' => 'US$',
                'position' => 'after',
                'rounding' => '0.001',
            ])
            ->assertRedirect(route('app.accounting.currencies.index'));

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'US Dollar',
            'symbol' => 'US$',
            'position' => 'after',
        ]);
    }

    public function test_decimal_places_are_derived_from_rounding(): void
    {
        $currency = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();

        $currency->update(['rounding' => '0.0001']);
        $this->assertSame(4, $currency->fresh()->decimal_places);

        $currency->update(['rounding' => '0.01']);
        $this->assertSame(2, $currency->fresh()->decimal_places);

        $currency->update(['rounding' => '1']);
        $this->assertSame(0, $currency->fresh()->decimal_places);
    }

    public function test_foreign_currency_can_be_archived_and_restored(): void
    {
        $usd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->post("/app/accounting/currencies/{$usd->id}/archive")
            ->assertRedirect(route('app.accounting.currencies.index'));

        $this->assertFalse($usd->fresh()->active);

        $this->actingAs($this->tenantAdmin)
            ->post("/app/accounting/currencies/{$usd->id}/restore")
            ->assertRedirect(route('app.accounting.currencies.index'));

        $this->assertTrue($usd->fresh()->active);
    }

    public function test_functional_currency_cannot_be_archived(): void
    {
        $try = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'TRY')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->post("/app/accounting/currencies/{$try->id}/archive")
            ->assertSessionHasErrors('currency');

        $this->assertTrue($try->fresh()->active);
    }

    public function test_archived_and_unused_foreign_currency_can_be_deleted(): void
    {
        $currency = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'EUR')->firstOrFail();
        $currency->update(['active' => false]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/currencies/{$currency->id}")
            ->assertRedirect(route('app.accounting.currencies.index'));

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_active_currency_cannot_be_deleted_before_archiving(): void
    {
        $currency = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'EUR')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/currencies/{$currency->id}")
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_functional_currency_cannot_be_deleted(): void
    {
        $try = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'TRY')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/currencies/{$try->id}")
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseHas('currencies', ['id' => $try->id]);
    }

    public function test_currency_with_exchange_rate_cannot_be_deleted(): void
    {
        $usd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();
        $usd->update(['active' => false]);

        ExchangeRate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $usd->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/currencies/{$usd->id}")
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseHas('currencies', ['id' => $usd->id]);
    }

    public function test_currency_referenced_by_an_invoice_cannot_be_deleted(): void
    {
        $usd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();
        $usd->update(['active' => false]);

        $po = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id]);

        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $po->partner_id,
            'source_type' => 'purchase_order',
            'source_id' => $po->id,
            'currency_id' => $usd->id,
            'exchange_rate_used' => '30.000000',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/currencies/{$usd->id}")
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseHas('currencies', ['id' => $usd->id]);
    }

    public function test_currencies_are_scoped_to_the_current_tenant(): void
    {
        $usd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();

        $otherTenant = Tenant::factory()->create();
        app(AccountingDefaultsService::class)->provision($otherTenant);
        $otherUsd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $otherTenant->id)->where('code', 'USD')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->post("/app/accounting/currencies/{$otherUsd->id}/archive")
            ->assertNotFound();

        $this->assertTrue($otherUsd->fresh()->active);
        $this->assertTrue($usd->fresh()->active);
    }

    public function test_show_page_lists_rate_history_and_inverse(): void
    {
        $usd = Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();

        ExchangeRate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $usd->id,
            'buy_rate' => '30.000000',
            'sell_rate' => '30.500000',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get("/app/accounting/currencies/{$usd->id}")
            ->assertOk()
            ->assertSee('30.000000')
            ->assertSee('0.033333');
    }

    public function test_user_without_permission_cannot_access_currencies_page(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/accounting/currencies')
            ->assertForbidden();
    }
}
