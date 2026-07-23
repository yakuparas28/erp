<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountingDefaultsService;
use Tests\TenantTestCase;

class CurrencyModelTest extends TenantTestCase
{
    public function test_provision_creates_the_three_default_currencies(): void
    {
        $tenant = Tenant::factory()->create();

        app(AccountingDefaultsService::class)->provision($tenant);

        $this->assertSame(3, Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $try = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'TRY')->firstOrFail();
        $usd = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'USD')->firstOrFail();
        $eur = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'EUR')->firstOrFail();

        $this->assertTrue($try->is_functional);
        $this->assertFalse($usd->is_functional);
        $this->assertFalse($eur->is_functional);
    }

    public function test_provision_is_idempotent_for_currencies(): void
    {
        $tenant = Tenant::factory()->create();

        app(AccountingDefaultsService::class)->provision($tenant);
        app(AccountingDefaultsService::class)->provision($tenant);

        $this->assertSame(3, Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_invoice_exchange_rate_or_one_returns_default_when_currency_id_is_null(): void
    {
        $invoice = Invoice::factory()->create(['currency_id' => null, 'exchange_rate_used' => null]);

        $this->assertSame('1.000000', $invoice->exchangeRateOrOne());
    }

    public function test_invoice_exchange_rate_or_one_returns_stored_value_when_present(): void
    {
        $invoice = Invoice::factory()->create(['exchange_rate_used' => '32.500000']);

        $this->assertSame('32.500000', $invoice->exchangeRateOrOne());
    }

    public function test_payment_exchange_rate_or_one_returns_default_when_currency_id_is_null(): void
    {
        $payment = Payment::factory()->create(['currency_id' => null, 'exchange_rate_used' => null]);

        $this->assertSame('1.000000', $payment->exchangeRateOrOne());
    }

    public function test_payment_exchange_rate_or_one_returns_stored_value_when_present(): void
    {
        $payment = Payment::factory()->create(['exchange_rate_used' => '32.500000']);

        $this->assertSame('32.500000', $payment->exchangeRateOrOne());
    }
}
