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
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Tests\TenantTestCase;

class PurchaseReceiptAccountingTest extends TenantTestCase
{
    private function provisionCategory(Tenant $tenant): ProductCategory
    {
        app(AccountingDefaultsService::class)->provision($tenant);
        $defaults = app(AccountingDefaultsService::class);

        return ProductCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'stock_input_account_id' => $defaults->accountByCode($tenant->id, '153')->id,
            'expense_account_id' => $defaults->accountByCode($tenant->id, '621')->id,
        ]);
    }

    /**
     * @return array{0: PurchaseOrderLine, 1: Tenant}
     */
    private function receiveLine(Tenant $tenant, ?ProductCategory $category, string $qty, string $unitPrice): array
    {
        setPermissionsTeamId($tenant->id);
        $admin = User::factory()->for($tenant)->create();
        $admin->assignRole('Tenant Admin');
        $officer = User::factory()->for($tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $this->actingAs($admin);

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $dock = Location::factory()->create(['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $tenant->id, 'uom_category_id' => $uomCategory->id]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'uom_id' => $unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $category?->id,
        ]);

        $service = app(PurchaseOrderService::class);
        $po = $service->create($tenant->id, $supplier->id, $officer);
        $service->addLine($po, $product->id, $unit->id, $qty, $unitPrice);
        $service->sendRfq($po);
        $service->confirm($po->fresh(), $admin);

        $line = $po->fresh()->lines()->firstOrFail();
        $service->receive($line, $qty, $dock->id);

        return [$line, $tenant];
    }

    public function test_receiving_posts_stock_debit_for_anglo_saxon_tenant(): void
    {
        $tenant = Tenant::factory()->angloSaxon()->create();
        $category = $this->provisionCategory($tenant);
        $defaults = app(AccountingDefaultsService::class);

        [$line] = $this->receiveLine($tenant, $category, '10', '7.5000');

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('posted', $entry->status);

        $stockAccount = $defaults->accountByCode($tenant->id, '153');
        $payablesAccount = $defaults->accountByCode($tenant->id, '320');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $stockAccount->id)->firstOrFail();
        $creditLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $payablesAccount->id)->firstOrFail();

        $this->assertSame('75.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('0.0000', $creditLine->debit);
        $this->assertSame('75.0000', $creditLine->credit);
    }

    public function test_receiving_posts_expense_debit_for_continental_tenant(): void
    {
        $tenant = Tenant::factory()->create(); // default accounting_mode: continental
        $category = $this->provisionCategory($tenant);
        $defaults = app(AccountingDefaultsService::class);

        $this->receiveLine($tenant, $category, '4', '10.0000');

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $expenseAccount = $defaults->accountByCode($tenant->id, '621');
        $payablesAccount = $defaults->accountByCode($tenant->id, '320');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $expenseAccount->id)->firstOrFail();
        $creditLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $payablesAccount->id)->firstOrFail();

        $this->assertSame('40.0000', $debitLine->debit);
        $this->assertSame('40.0000', $creditLine->credit);
    }

    public function test_receiving_without_a_product_category_does_not_post_a_journal_entry(): void
    {
        $tenant = Tenant::factory()->create();
        app(AccountingDefaultsService::class)->provision($tenant);

        $this->receiveLine($tenant, null, '5', '3.0000');

        $this->assertDatabaseCount('journal_entries', 0);
    }
}
