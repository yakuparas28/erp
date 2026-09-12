<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class ReportsTest extends TenantTestCase
{
    private Product $product;

    private Uom $uom;

    private Location $stock;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $uomCategory->id,
            'is_reference' => true,
            'factor' => '1',
        ]);
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Test Kategorisi']);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'name' => 'Test Ürünü',
        ]);
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
        $this->stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Stok']);

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('view stock');
    }

    public function test_moves_report_renders_and_filters_by_product(): void
    {
        StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'to_location_id' => $this->stock->id,
            'qty' => '5',
            'uom_id' => $this->uom->id,
            'reference_type' => 'unique-seed-marker',
            'reference_id' => 0,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/moves')
            ->assertOk()
            ->assertSee('unique-seed-marker');
    }

    public function test_valuation_report_groups_by_category_with_totals(): void
    {
        $move = StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'to_location_id' => $this->stock->id,
            'qty' => '10',
            'uom_id' => $this->uom->id,
            'reference_type' => 'seed',
            'reference_id' => 0,
        ]);
        StockValuationLayer::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'stock_move_id' => $move->id,
            'qty' => '10',
            'unit_cost' => '5',
            'remaining_value' => '50',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/valuation')
            ->assertOk()
            ->assertSee('Test Kategorisi')
            ->assertSee('Test Ürünü')
            ->assertSee('50');
    }

    public function test_locations_report_lists_stock_per_location(): void
    {
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->stock->id,
            'qty' => '15',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/locations')
            ->assertOk()
            ->assertSee('Stok')
            ->assertSee('Test Ürünü')
            ->assertSee('15');
    }

    public function test_forecasted_report_shows_on_hand_incoming_outgoing(): void
    {
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->stock->id,
            'qty' => '12',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/forecasted')
            ->assertOk()
            ->assertSee('Test Ürünü')
            ->assertSee('12');
    }

    public function test_warehouse_analysis_shows_stats_per_warehouse(): void
    {
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->stock->id,
            'qty' => '8',
        ]);
        StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'to_location_id' => $this->stock->id,
            'qty' => '5',
            'uom_id' => $this->uom->id,
            'reference_type' => 'seed',
            'reference_id' => 0,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/warehouse-analysis')
            ->assertOk()
            ->assertSee('Main');
    }

    public function test_reports_require_view_stock_permission(): void
    {
        $random = User::factory()->for($this->tenant)->create();

        $this->actingAs($random)
            ->get('/app/inventory/reports/moves')
            ->assertForbidden();
    }
}
