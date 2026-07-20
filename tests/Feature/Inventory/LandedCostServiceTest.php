<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\LandedCostService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class LandedCostServiceTest extends TenantTestCase
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

    private function service(): LandedCostService
    {
        return app(LandedCostService::class);
    }

    public function test_by_quantity_distributes_proportional_to_move_quantity(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        $moveA = $this->move($productA, '30');
        $moveB = $this->move($productB, '10');
        app(CostingService::class)->recordInbound($productA, $moveA, '30', '5.0000');
        app(CostingService::class)->recordInbound($productB, $moveB, '10', '5.0000');

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'description' => 'Navlun', 'amount' => '400.0000']);

        $this->service()->validate($landedCost, [$moveA->id, $moveB->id], $this->tenantAdmin);

        // 30/40 * 400 = 300; 10/40 * 400 = 100
        $this->assertSame('300.0000', $landedCost->distributions()->where('stock_move_id', $moveA->id)->firstOrFail()->allocated_amount);
        $this->assertSame('100.0000', $landedCost->distributions()->where('stock_move_id', $moveB->id)->firstOrFail()->allocated_amount);

        // unit_cost geriye dönük güncellenir: 5 + 300/30 = 15
        $this->assertSame('15.0000', StockValuationLayer::where('stock_move_id', $moveA->id)->firstOrFail()->unit_cost);
        $this->assertSame('validated', $landedCost->fresh()->status);
    }

    public function test_by_current_cost_distributes_proportional_to_batch_value(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        $moveA = $this->move($productA, '10');
        $moveB = $this->move($productB, '10');
        app(CostingService::class)->recordInbound($productA, $moveA, '10', '10.0000'); // değer 100
        app(CostingService::class)->recordInbound($productB, $moveB, '10', '30.0000'); // değer 300

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_current_cost', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'amount' => '400.0000']);

        $this->service()->validate($landedCost, [$moveA->id, $moveB->id], $this->tenantAdmin);

        // 100/400*400=100 ; 300/400*400=300
        $this->assertSame('100.0000', $landedCost->distributions()->where('stock_move_id', $moveA->id)->firstOrFail()->allocated_amount);
        $this->assertSame('300.0000', $landedCost->distributions()->where('stock_move_id', $moveB->id)->firstOrFail()->allocated_amount);
    }

    public function test_by_weight_and_by_volume_use_product_dimensions(): void
    {
        $heavy = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo', 'weight' => '10.0000']);
        $light = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo', 'weight' => '2.0000']);

        $moveHeavy = $this->move($heavy, '1');
        $moveLight = $this->move($light, '1');
        app(CostingService::class)->recordInbound($heavy, $moveHeavy, '1', '1.0000');
        app(CostingService::class)->recordInbound($light, $moveLight, '1', '1.0000');

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_weight', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'amount' => '120.0000']);

        $this->service()->validate($landedCost, [$moveHeavy->id, $moveLight->id], $this->tenantAdmin);

        // 10/12*120=100 ; 2/12*120=20
        $this->assertSame('100.0000', $landedCost->distributions()->where('stock_move_id', $moveHeavy->id)->firstOrFail()->allocated_amount);
        $this->assertSame('20.0000', $landedCost->distributions()->where('stock_move_id', $moveLight->id)->firstOrFail()->allocated_amount);
    }

    public function test_standard_costed_product_move_rejects_landed_cost_with_409(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id,
            'cost_method' => 'standard', 'standard_cost' => '5.0000',
        ]);
        $move = $this->move($product, '10');
        app(CostingService::class)->recordInbound($product, $move, '10', '5.0000');

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'amount' => '50.0000']);

        try {
            $this->service()->validate($landedCost, [$move->id], $this->tenantAdmin);
            $this->fail('409 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame('draft', $landedCost->fresh()->status);
    }

    public function test_user_without_permission_cannot_validate_landed_cost(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $move = $this->move($product, '10');
        app(CostingService::class)->recordInbound($product, $move, '10', '5.0000');

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'amount' => '50.0000']);

        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        try {
            $this->service()->validate($landedCost, [$move->id], $operator);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_validated_landed_cost_cannot_be_validated_again(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $move = $this->move($product, '10');
        app(CostingService::class)->recordInbound($product, $move, '10', '5.0000');

        $landedCost = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $landedCost->id, 'amount' => '50.0000']);

        $this->service()->validate($landedCost, [$move->id], $this->tenantAdmin);

        try {
            $this->service()->validate($landedCost->fresh(), [$move->id], $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
