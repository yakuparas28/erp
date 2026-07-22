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
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

class SalesDeliveryAccountingTest extends TenantTestCase
{
    private Location $location;

    private Uom $unit;

    private function provisionCategory(Tenant $tenant): ProductCategory
    {
        app(AccountingDefaultsService::class)->provision($tenant);
        $defaults = app(AccountingDefaultsService::class);

        return ProductCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'stock_output_account_id' => $defaults->accountByCode($tenant->id, '153')->id,
            'expense_account_id' => $defaults->accountByCode($tenant->id, '621')->id,
        ]);
    }

    private function setUpTenant(Tenant $tenant): User
    {
        setPermissionsTeamId($tenant->id);
        $admin = User::factory()->for($tenant)->create();
        $admin->assignRole('Tenant Admin');
        $rep = User::factory()->for($tenant)->create();
        $rep->assignRole('Sales Representative');

        $this->actingAs($admin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $tenant->id, 'uom_category_id' => $uomCategory->id]);

        return $admin;
    }

    private function stockIn(Tenant $tenant, Product $product, string $qty, string $unitCost): void
    {
        $move = app(StockMoveService::class)->move(
            tenantId: $tenant->id,
            product: $product,
            fromLocationId: null,
            toLocationId: $this->location->id,
            qty: $qty,
            uom: $this->unit,
            referenceType: 'inventory_adjustment',
            referenceId: 1,
        );

        app(CostingService::class)->recordInbound($product, $move, $qty, $unitCost);
    }

    /**
     * @return array{0: SalesOrder, 1: SalesOrderLine, 2: User}
     */
    private function confirmedOrder(Tenant $tenant, Product $product, string $qty, string $unitPrice): array
    {
        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->role('Tenant Admin')->firstOrFail();
        $rep = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->role('Sales Representative')->firstOrFail();
        $customer = Partner::factory()->customer()->create(['tenant_id' => $tenant->id]);

        $service = app(SalesOrderService::class);
        $so = $service->create($tenant->id, $customer->id, $this->location->id, $rep);
        $line = $service->addLine($so, $product->id, $this->unit->id, $qty, $unitPrice);
        $service->sendQuotation($so);
        $service->confirm($so->fresh(), $admin);

        return [$so, $line, $admin];
    }

    public function test_delivering_a_normal_line_posts_fifo_cogs_for_anglo_saxon_tenant(): void
    {
        $tenant = Tenant::factory()->angloSaxon()->create();
        $this->setUpTenant($tenant);
        $category = $this->provisionCategory($tenant);
        $defaults = app(AccountingDefaultsService::class);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'uom_id' => $this->unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $category->id,
        ]);

        // İki farklı maliyetli FIFO katmanı: 10 birim @ 5.00, 10 birim @ 8.00
        $this->stockIn($tenant, $product, '10', '5.0000');
        $this->stockIn($tenant, $product, '10', '8.0000');

        [, $line] = $this->confirmedOrder($tenant, $product, '15', '20.0000');

        app(SalesOrderService::class)->deliver($line, '15');

        // Beklenen COGS: 10*5 + 5*8 = 90.0000
        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('posted', $entry->status);

        $expenseAccount = $defaults->accountByCode($tenant->id, '621');
        $stockAccount = $defaults->accountByCode($tenant->id, '153');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $expenseAccount->id)->firstOrFail();
        $creditLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $stockAccount->id)->firstOrFail();

        $this->assertSame('90.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('0.0000', $creditLine->debit);
        $this->assertSame('90.0000', $creditLine->credit);
    }

    public function test_delivering_a_line_for_continental_tenant_posts_no_journal_entry(): void
    {
        $tenant = Tenant::factory()->create(); // continental (default)
        $this->setUpTenant($tenant);
        $category = $this->provisionCategory($tenant);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'uom_id' => $this->unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $category->id,
        ]);

        $this->stockIn($tenant, $product, '10', '5.0000');

        [, $line] = $this->confirmedOrder($tenant, $product, '5', '20.0000');

        app(SalesOrderService::class)->deliver($line, '5');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_delivering_a_kit_line_posts_a_separate_journal_entry_per_component(): void
    {
        $tenant = Tenant::factory()->angloSaxon()->create();
        $this->setUpTenant($tenant);
        $categoryA = $this->provisionCategory($tenant);

        $defaults = app(AccountingDefaultsService::class);
        $categoryB = ProductCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'stock_output_account_id' => $defaults->accountByCode($tenant->id, '153')->id,
            'expense_account_id' => $defaults->accountByCode($tenant->id, '621')->id,
        ]);

        $componentA = Product::factory()->create([
            'tenant_id' => $tenant->id, 'uom_id' => $this->unit->id,
            'cost_method' => 'fifo', 'product_category_id' => $categoryA->id,
        ]);
        $componentB = Product::factory()->create([
            'tenant_id' => $tenant->id, 'uom_id' => $this->unit->id,
            'cost_method' => 'fifo', 'product_category_id' => $categoryB->id,
        ]);
        $kit = Product::factory()->create([
            'tenant_id' => $tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true,
        ]);

        ProductKitComponent::factory()->create([
            'tenant_id' => $tenant->id, 'kit_product_id' => $kit->id,
            'component_product_id' => $componentA->id, 'qty' => '1',
        ]);
        ProductKitComponent::factory()->create([
            'tenant_id' => $tenant->id, 'kit_product_id' => $kit->id,
            'component_product_id' => $componentB->id, 'qty' => '2',
        ]);

        $this->stockIn($tenant, $componentA, '10', '3.0000');
        $this->stockIn($tenant, $componentB, '10', '4.0000');

        [, $line] = $this->confirmedOrder($tenant, $kit, '3', '20.0000');

        app(SalesOrderService::class)->deliver($line, '3');

        $this->assertDatabaseCount('journal_entries', 2);

        $moveA = StockMove::where('product_id', $componentA->id)
            ->where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();
        $moveB = StockMove::where('product_id', $componentB->id)
            ->where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();

        $entryA = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('reference_type', $moveA->getMorphClass())
            ->where('reference_id', $moveA->id)->firstOrFail();
        $entryB = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('reference_type', $moveB->getMorphClass())
            ->where('reference_id', $moveB->id)->firstOrFail();

        $expenseAccount = $defaults->accountByCode($tenant->id, '621');

        $debitLineA = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entryA->id)->where('account_id', $expenseAccount->id)->firstOrFail();
        $this->assertSame('9.0000', $debitLineA->debit); // 3 * 3.00

        $debitLineB = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entryB->id)->where('account_id', $expenseAccount->id)->firstOrFail();
        $this->assertSame('24.0000', $debitLineB->debit); // 6 * 4.00
    }

    public function test_delivering_a_line_without_a_product_category_posts_no_journal_entry(): void
    {
        $tenant = Tenant::factory()->angloSaxon()->create();
        $this->setUpTenant($tenant);
        app(AccountingDefaultsService::class)->provision($tenant);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'uom_id' => $this->unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => null,
        ]);

        $this->stockIn($tenant, $product, '10', '5.0000');

        [, $line] = $this->confirmedOrder($tenant, $product, '5', '20.0000');

        app(SalesOrderService::class)->deliver($line, '5');

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame('5.0000', $line->fresh()->delivered_qty);
    }
}
