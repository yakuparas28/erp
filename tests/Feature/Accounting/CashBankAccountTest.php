<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Tests\TenantTestCase;

class CashBankAccountTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_admin_can_add_a_bank_account_with_iban_and_linked_coa(): void
    {
        $bankAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', 'like', '102%')
            ->firstOrFail();

        $response = $this->post(route('app.accounting.cash-bank-accounts.store'), [
            'name' => 'Vakıfbank TL',
            'type' => 'bank',
            'code' => 'VKF-TL',
            'bank_name' => 'Vakıfbank',
            'iban' => 'TR000000000000000000000000',
            'chart_of_account_id' => $bankAccount->id,
            'opening_balance' => '5000',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('journals', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Vakıfbank TL',
            'type' => 'bank',
            'code' => 'VKF-TL',
            'chart_of_account_id' => $bankAccount->id,
        ]);
    }

    public function test_admin_cannot_use_the_same_code_twice_within_tenant(): void
    {
        Journal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Merkez Kasa',
            'type' => 'cash',
            'code' => 'KASA-01',
        ]);

        $response = $this->post(route('app.accounting.cash-bank-accounts.store'), [
            'name' => 'İkinci Kasa',
            'type' => 'cash',
            'code' => 'KASA-01',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_destroy_archives_the_account_when_it_has_activity_or_no_coa(): void
    {
        $journal = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Eski Kasa',
            'type' => 'cash',
            'is_active' => true,
            // no chart_of_account_id → soft-archive path
        ]);

        $this->delete(route('app.accounting.cash-bank-accounts.destroy', $journal))->assertRedirect();

        $this->assertDatabaseHas('journals', ['id' => $journal->id, 'is_active' => false]);
    }

    public function test_non_admin_cannot_access_the_management_screen(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole('Accountant');

        $this->actingAs($user)
            ->get(route('app.accounting.cash-bank-accounts.index'))
            ->assertForbidden();
    }
}
