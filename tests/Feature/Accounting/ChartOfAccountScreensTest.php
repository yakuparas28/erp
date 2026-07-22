<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Tests\TenantTestCase;

class ChartOfAccountScreensTest extends TenantTestCase
{
    public function test_accounts_index_lists_existing_accounts(): void
    {
        ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => '100',
            'name' => 'Kasa',
            'type' => 'asset',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/accounting/accounts')
            ->assertOk()
            ->assertSee('100')
            ->assertSee('Kasa');
    }

    public function test_new_account_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/accounting/accounts', [
            'code' => '120',
            'name' => 'Alıcılar',
            'type' => 'asset',
        ])->assertRedirect(route('app.accounting.accounts.index'));

        $this->assertDatabaseHas('chart_of_accounts', [
            'tenant_id' => $this->tenant->id,
            'code' => '120',
            'name' => 'Alıcılar',
            'type' => 'asset',
        ]);
    }

    public function test_unused_account_can_be_deleted(): void
    {
        $account = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$account->id}")
            ->assertRedirect(route('app.accounting.accounts.index'));

        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_account_used_in_journal_entry_lines_cannot_be_deleted(): void
    {
        $account = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);
        $entry = JournalEntry::factory()->create(['tenant_id' => $this->tenant->id, 'journal_id' => $journal->id]);
        JournalEntryLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/accounting/accounts/{$account->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $account->id]);
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
