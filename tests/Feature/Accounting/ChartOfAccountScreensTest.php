<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Tests\TenantTestCase;

class ChartOfAccountScreensTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    public function test_accounts_index_lists_provisioned_tdhp_accounts_grouped_by_class(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/accounting/accounts')
            ->assertOk()
            ->assertSee('100')
            ->assertSee('Kasa')
            ->assertSee('320')
            ->assertSee('Satıcılar')
            ->assertSee('600')
            ->assertSee('Yurtiçi Satışlar');
    }

    public function test_tdhp_accounts_are_flagged_as_system(): void
    {
        $account = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();

        $this->assertTrue($account->is_system);
        $this->assertNull($account->parent_id);
    }

    public function test_sub_account_can_be_created_under_a_system_account(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/accounts', [
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
        ])->assertRedirect(route('app.accounting.accounts.index'));

        $this->assertDatabaseHas('chart_of_accounts', [
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
            'type' => 'asset',
            'is_system' => false,
        ]);
    }

    public function test_sub_account_code_must_start_with_parent_code_and_a_dot(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/accounts', [
            'parent_id' => $parent->id,
            'code' => '99.01',
            'name' => 'Wrong Parent',
        ])->assertSessionHasErrors('code');
    }

    public function test_sub_account_inherits_the_parent_type(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '320')->firstOrFail();

        $this->actingAs($this->tenantAdmin)->post('/app/accounting/accounts', [
            'parent_id' => $parent->id,
            'code' => '320.01',
            'name' => 'Piyasa Satıcıları',
        ]);

        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '320.01',
            'type' => 'liability',
        ]);
    }

    public function test_system_account_cannot_be_deleted(): void
    {
        $account = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$account->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_sub_account_with_children_cannot_be_deleted(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();
        $sub = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
            'type' => 'asset',
            'is_system' => false,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $sub->id,
            'code' => '100.01.001',
            'name' => 'TL Kasası',
            'type' => 'asset',
            'is_system' => false,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$sub->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $sub->id]);
    }

    public function test_leaf_sub_account_without_journal_entries_can_be_deleted(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();
        $sub = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
            'type' => 'asset',
            'is_system' => false,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$sub->id}")
            ->assertRedirect(route('app.accounting.accounts.index'));

        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $sub->id]);
    }

    public function test_sub_account_used_in_journal_entry_lines_cannot_be_deleted(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();
        $sub = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
            'type' => 'asset',
            'is_system' => false,
        ]);
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);
        $entry = JournalEntry::factory()->create(['tenant_id' => $this->tenant->id, 'journal_id' => $journal->id]);
        JournalEntryLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $sub->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$sub->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $sub->id]);
    }

    public function test_sub_account_name_can_be_updated(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '100')->firstOrFail();
        $sub = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa',
            'type' => 'asset',
            'is_system' => false,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch("/app/accounting/accounts/{$sub->id}", ['name' => 'Ana Kasa'])
            ->assertRedirect(route('app.accounting.accounts.index'));

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $sub->id, 'name' => 'Ana Kasa']);
    }

    public function test_user_without_permission_cannot_access_accounts_page(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/accounting/accounts')
            ->assertForbidden();
    }
}
