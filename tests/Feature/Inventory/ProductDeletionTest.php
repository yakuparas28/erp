<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockMoveService;
use Tests\TenantTestCase;

class ProductDeletionTest extends TenantTestCase
{
    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    public function test_product_without_stock_history_can_be_deleted(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        $this->delete("/app/inventory/products/{$product->id}")->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_with_stock_moves_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $product,
            fromLocationId: null, toLocationId: $location->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        $this->from('/app/inventory/products')
            ->delete("/app/inventory/products/{$product->id}")
            ->assertRedirect('/app/inventory/products')
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_used_as_a_kit_component_elsewhere_cannot_be_deleted(): void
    {
        $component = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);
        ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $kit->id, 'component_product_id' => $component->id,
        ]);

        $this->from('/app/inventory/products')
            ->delete("/app/inventory/products/{$component->id}")
            ->assertRedirect('/app/inventory/products')
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('products', ['id' => $component->id]);
    }

    public function test_deleting_a_kit_removes_its_own_component_links(): void
    {
        $component = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);
        $link = ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $kit->id, 'component_product_id' => $component->id,
        ]);

        $this->delete("/app/inventory/products/{$kit->id}")->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $kit->id]);
        $this->assertDatabaseMissing('product_kit_components', ['id' => $link->id]);
        $this->assertDatabaseHas('products', ['id' => $component->id]);
    }
}
