<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Tests\TenantTestCase;

class AccountingDefaultsServiceTest extends TenantTestCase
{
    public function test_accounting_defaults_are_provisioned_for_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        app(AccountingDefaultsService::class)->provision($tenant);

        $this->assertSame(269, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, TaxRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $this->assertTrue(
            ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', '191')->exists(),
        );
        $this->assertTrue(
            Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'sale')->exists(),
        );
        $this->assertTrue(
            TaxRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'KDV %20 (Satış)')->exists(),
        );
    }

    public function test_provision_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();

        app(AccountingDefaultsService::class)->provision($tenant);
        app(AccountingDefaultsService::class)->provision($tenant);

        $this->assertSame(269, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, TaxRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_account_by_code_returns_the_matching_account(): void
    {
        $tenant = Tenant::factory()->create();
        app(AccountingDefaultsService::class)->provision($tenant);

        $account = app(AccountingDefaultsService::class)->accountByCode($tenant->id, '100');

        $this->assertSame('Kasa', $account->name);
    }

    public function test_account_by_code_throws_when_code_is_missing(): void
    {
        $tenant = Tenant::factory()->create();
        app(AccountingDefaultsService::class)->provision($tenant);

        $this->expectException(ModelNotFoundException::class);

        app(AccountingDefaultsService::class)->accountByCode($tenant->id, '999');
    }
}
