<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\KitExplosionService;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class KitExplosionTest extends TenantTestCase
{
    private Location $location;

    private Uom $unit;

    private Product $kit;

    private Product $componentA;

    private Product $componentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);

        $this->componentA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Kulaklık']);
        $this->componentB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Şarj Kablosu']);
        $this->kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Başlangıç Seti', 'is_kit' => true]);

        ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $this->kit->id,
            'component_product_id' => $this->componentA->id, 'qty' => '1',
        ]);
        ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $this->kit->id,
            'component_product_id' => $this->componentB->id, 'qty' => '2',
        ]);
    }

    private function stock(Product $product, string $qty): void
    {
        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: $qty, uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );
    }

    private function service(): KitExplosionService
    {
        return app(KitExplosionService::class);
    }

    public function test_kit_never_receives_stock_moves_or_quants(): void
    {
        $this->stock($this->componentA, '10');
        $this->stock($this->componentB, '10');

        $this->service()->explode(
            kit: $this->kit, kitQty: '3', fromLocationId: $this->location->id, toLocationId: null,
            referenceType: 'warehouse_transfer', referenceId: 1,
        );

        $this->assertSame(0, StockMove::where('product_id', $this->kit->id)->count());
        $this->assertSame(0, StockQuant::where('product_id', $this->kit->id)->count());
    }

    public function test_kit_explosion_moves_components_by_quantity(): void
    {
        $this->stock($this->componentA, '10');
        $this->stock($this->componentB, '10');

        $this->service()->explode(
            kit: $this->kit, kitQty: '3', fromLocationId: $this->location->id, toLocationId: null,
            referenceType: 'warehouse_transfer', referenceId: 1,
        );

        // 3 kit x 1 = 3 componentA çıkışı; 3 kit x 2 = 6 componentB çıkışı
        $this->assertSame('7.0000', StockQuant::where('product_id', $this->componentA->id)->firstOrFail()->qty);
        $this->assertSame('4.0000', StockQuant::where('product_id', $this->componentB->id)->firstOrFail()->qty);
    }

    public function test_insufficient_component_stock_rejects_entire_kit_line(): void
    {
        $this->stock($this->componentA, '10');
        $this->stock($this->componentB, '1'); // 3 kit x 2 = 6 gerekli, yetersiz

        try {
            $this->service()->explode(
                kit: $this->kit, kitQty: '3', fromLocationId: $this->location->id, toLocationId: null,
                referenceType: 'warehouse_transfer', referenceId: 1,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // Atomik: componentA'nın stoğu da değişmemiş olmalı
        $this->assertSame('10.0000', StockQuant::where('product_id', $this->componentA->id)->firstOrFail()->qty);
    }

    public function test_component_cannot_itself_be_a_kit(): void
    {
        $nestedKit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);

        try {
            ProductKitComponent::create([
                'kit_product_id' => $this->kit->id,
                'component_product_id' => $nestedKit->id,
                'qty' => '1',
            ]);
            $this->service()->explode(
                kit: $this->kit, kitQty: '1', fromLocationId: $this->location->id, toLocationId: null,
                referenceType: 'warehouse_transfer', referenceId: 2,
            );
            $this->fail('422 bekleniyordu (iç içe kit)');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_non_kit_product_cannot_be_exploded(): void
    {
        try {
            $this->service()->explode(
                kit: $this->componentA, kitQty: '1', fromLocationId: $this->location->id, toLocationId: null,
                referenceType: 'warehouse_transfer', referenceId: 1,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
