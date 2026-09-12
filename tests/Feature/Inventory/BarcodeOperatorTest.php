<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class BarcodeOperatorTest extends TenantTestCase
{
    private Product $product;

    private Uom $uom;

    private Location $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->uom->id, 'track_by' => 'none', 'name' => 'Barkodlu Ürün']);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        ProductBarcode::create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'barcode' => 'BC-001']);

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('perform stock counts');
    }

    public function test_lookup_resolves_known_barcode(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->getJson('/app/inventory/barcode/lookup?barcode=BC-001')
            ->assertOk()
            ->assertJson(['found' => true, 'product' => ['name' => 'Barkodlu Ürün']]);
    }

    public function test_lookup_returns_404_for_unknown_barcode(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->getJson('/app/inventory/barcode/lookup?barcode=UNKNOWN')
            ->assertNotFound();
    }

    public function test_move_records_stock_via_barcode(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/barcode/move', [
            'barcode' => 'BC-001',
            'to_location_id' => $this->stock->id,
            'qty' => '5',
        ])->assertRedirect();

        $this->assertSame('5.0000', (string) StockQuant::where('location_id', $this->stock->id)->where('product_id', $this->product->id)->value('qty'));
        $this->assertSame(1, StockMove::where('reference_type', 'barcode_operator')->count());
    }

    public function test_unknown_barcode_move_shows_error(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/barcode/move', [
            'barcode' => 'INVALID',
            'to_location_id' => $this->stock->id,
            'qty' => '1',
        ])->assertSessionHasErrors('barcode');
    }
}
