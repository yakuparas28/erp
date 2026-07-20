<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\PutawayRule;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\PutawayService;
use Tests\TenantTestCase;

class PutawayServiceTest extends TenantTestCase
{
    private Warehouse $warehouse;

    private Location $source;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->source = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Mal Kabul']);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
    }

    private function service(): PutawayService
    {
        return app(PutawayService::class);
    }

    public function test_product_specific_rule_matches_before_category_rule_by_sequence(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'product_category_id' => $category->id]);

        $categoryDest = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Kategori Rafı']);
        $productDest = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Özel Raf']);

        PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => null, 'product_category_id' => $category->id,
            'source_location_id' => $this->source->id, 'dest_location_id' => $categoryDest->id, 'sequence' => 1,
        ]);
        PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'product_category_id' => null,
            'source_location_id' => $this->source->id, 'dest_location_id' => $productDest->id, 'sequence' => 2,
        ]);

        // sequence=1 olan kategori kuralı önce eşleşir (ürüne özel olması onu öncelikli yapmaz).
        $this->assertSame($categoryDest->id, $this->service()->resolveDestination($product, $this->source->id));
    }

    public function test_lower_sequence_wins_when_product_rule_comes_first(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $productDest = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id]);

        PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id,
            'source_location_id' => $this->source->id, 'dest_location_id' => $productDest->id, 'sequence' => 1,
        ]);

        $this->assertSame($productDest->id, $this->service()->resolveDestination($product, $this->source->id));
    }

    public function test_returns_null_when_no_rule_matches(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        $this->assertNull($this->service()->resolveDestination($product, $this->source->id));
    }
}
