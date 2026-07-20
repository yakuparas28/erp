<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\CostingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class CostingEngineTest extends TenantTestCase
{
    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    private function move(Product $product, string $qty): StockMove
    {
        $move = StockMove::create([
            'product_id' => $product->id,
            'from_location_id' => null,
            'to_location_id' => null,
            'uom_id' => $this->unit->id,
            'qty' => $qty,
            'reference_type' => 'inventory_adjustment',
            'reference_id' => 1,
        ]);
        $move->tenant_id = $this->tenant->id;
        $move->save();

        return $move;
    }

    private function service(): CostingService
    {
        return app(CostingService::class);
    }

    public function test_fifo_consumes_layers_oldest_first_across_multiple_batches(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        $this->service()->recordInbound($product, $this->move($product, '5'), '5', '10.0000');
        $this->service()->recordInbound($product, $this->move($product, '5'), '5', '20.0000');

        $cogs = $this->service()->consumeOutbound($product, $this->move($product, '-7'), '7');

        // 5 adet @10 + 2 adet @20 = 90
        $this->assertSame('90.0000', $cogs);
    }

    public function test_fifo_moves_to_next_layer_when_one_is_exhausted(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        $this->service()->recordInbound($product, $this->move($product, '3'), '3', '10.0000');
        $this->service()->recordInbound($product, $this->move($product, '3'), '3', '30.0000');

        $this->service()->consumeOutbound($product, $this->move($product, '-3'), '3');

        $remaining = StockValuationLayer::where('product_id', $product->id)->where('unit_cost', '10.0000')->firstOrFail();
        $this->assertSame('0.0000', $remaining->remaining_value);

        $secondLayer = StockValuationLayer::where('product_id', $product->id)->where('unit_cost', '30.0000')->firstOrFail();
        $this->assertSame('3.0000', $secondLayer->qty);
    }

    public function test_avco_recalculates_weighted_average_and_caches_on_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'avco']);

        $this->service()->recordInbound($product, $this->move($product, '5'), '5', '10.0000');
        $this->service()->recordInbound($product, $this->move($product, '5'), '5', '20.0000');

        // (5*10 + 5*20) / 10 = 15
        $this->assertSame('15.0000', $product->fresh()->avco_unit_cost);

        $cogs = $this->service()->consumeOutbound($product, $this->move($product, '-7'), '7');
        $this->assertSame('105.0000', $cogs);
    }

    public function test_standard_costing_always_uses_fixed_product_cost(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id,
            'cost_method' => 'standard', 'standard_cost' => '12.0000',
        ]);

        // Girişte farklı bir birim maliyet gönderilse bile sabit değer kullanılır.
        $this->service()->recordInbound($product, $this->move($product, '10'), '10', '999.0000');

        $cogs = $this->service()->consumeOutbound($product, $this->move($product, '-4'), '4');

        $this->assertSame('48.0000', $cogs);
    }

    public function test_cost_method_cannot_change_after_first_valuation_layer(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->service()->recordInbound($product, $this->move($product, '1'), '1', '10.0000');

        try {
            $product->update(['cost_method' => 'avco']);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('fifo', $product->fresh()->cost_method);
    }

    public function test_cost_method_can_be_set_before_any_valuation_layer(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        $product->update(['cost_method' => 'avco']);

        $this->assertSame('avco', $product->fresh()->cost_method);
    }
}
