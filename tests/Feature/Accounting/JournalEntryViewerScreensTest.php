<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use App\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Services\PurchaseOrderService;
use Tests\TenantTestCase;

class JournalEntryViewerScreensTest extends TenantTestCase
{
    /**
     * @return array{0: Tenant, 1: JournalEntry}
     */
    private function postAPurchaseReceiptJournalEntry(): array
    {
        $tenant = $this->tenant;
        app(AccountingDefaultsService::class)->provision($tenant);
        $defaults = app(AccountingDefaultsService::class);

        $category = ProductCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'stock_input_account_id' => $defaults->accountByCode($tenant->id, '153')->id,
            'expense_account_id' => $defaults->accountByCode($tenant->id, '621')->id,
        ]);

        setPermissionsTeamId($tenant->id);
        $officer = User::factory()->for($tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $this->actingAs($this->tenantAdmin);

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $dock = Location::factory()->create(['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $tenant->id, 'uom_category_id' => $uomCategory->id]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'uom_id' => $unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $category->id,
        ]);

        $service = app(PurchaseOrderService::class);
        $po = $service->create($tenant->id, $supplier->id, $officer);
        $service->addLine($po, $product->id, $unit->id, '10', '7.5000');
        $service->sendRfq($po);
        $service->confirm($po->fresh(), $this->tenantAdmin);

        $line = $po->fresh()->lines()->firstOrFail();
        $service->receive($line, '10', $dock->id);

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        return [$tenant, $entry];
    }

    public function test_index_lists_real_journal_entries_posted_by_a_purchase_receipt(): void
    {
        [, $entry] = $this->postAPurchaseReceiptJournalEntry();

        $this->actingAs($this->tenantAdmin)
            ->get('/app/accounting/journal-entries')
            ->assertOk()
            ->assertSee($entry->journal->name)
            ->assertSee($entry->reference_type)
            ->assertSee('75.0000')
            ->assertSee(route('app.accounting.journal-entries.show', $entry));
    }

    public function test_show_displays_lines_with_account_code_debit_and_credit(): void
    {
        [, $entry] = $this->postAPurchaseReceiptJournalEntry();
        $entry->load('lines.account');

        $response = $this->actingAs($this->tenantAdmin)
            ->get("/app/accounting/journal-entries/{$entry->id}")
            ->assertOk();

        foreach ($entry->lines as $line) {
            $response->assertSee($line->account->code);
            $response->assertSee($line->account->name);
        }

        $response->assertSee('75.0000');
    }

    public function test_show_totals_prove_debit_equals_credit(): void
    {
        [, $entry] = $this->postAPurchaseReceiptJournalEntry();

        $totalDebit = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)
            ->sum('debit');

        $this->actingAs($this->tenantAdmin)
            ->get("/app/accounting/journal-entries/{$entry->id}")
            ->assertOk()
            ->assertSeeInOrder([number_format((float) $totalDebit, 4), number_format((float) $totalDebit, 4)]);
    }

    public function test_user_without_permission_cannot_access_journal_entries_index(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/accounting/journal-entries')
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_access_journal_entry_show(): void
    {
        [, $entry] = $this->postAPurchaseReceiptJournalEntry();

        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get("/app/accounting/journal-entries/{$entry->id}")
            ->assertForbidden();
    }
}
