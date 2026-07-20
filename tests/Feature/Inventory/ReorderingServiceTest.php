<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\Event;
use Modules\Inventory\Events\ReplenishmentAcknowledged;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\ReorderingService;
use Modules\Inventory\Services\StockMoveService;
use Tests\TenantTestCase;

class ReorderingServiceTest extends TenantTestCase
{
    private Location $location;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
    }

    private function stock(string $qty, int $referenceId = 1): void
    {
        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: $qty, uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: $referenceId,
        );
    }

    public function test_auto_rule_creates_a_pending_suggestion_when_stock_drops_below_min(): void
    {
        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'auto',
        ]);

        $this->stock('20');
        $this->assertSame(0, ReplenishmentSuggestion::count());

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: $this->location->id, toLocationId: null,
            qty: '-15', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 2,
        );

        $suggestion = ReplenishmentSuggestion::firstOrFail();
        $this->assertSame('pending', $suggestion->status);
        // max_qty(50) - mevcut(5) = 45
        $this->assertSame('45.0000', $suggestion->suggested_qty);
    }

    public function test_manual_rule_does_not_auto_create_a_suggestion(): void
    {
        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'manual',
        ]);

        $this->stock('5');

        $this->assertSame(0, ReplenishmentSuggestion::count());
    }

    public function test_existing_pending_suggestion_is_updated_not_duplicated(): void
    {
        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'auto',
        ]);

        $this->stock('3', 1);
        $this->stock('1', 2);

        $this->assertSame(1, ReplenishmentSuggestion::count());
        $this->assertSame('46.0000', ReplenishmentSuggestion::firstOrFail()->suggested_qty);
    }

    public function test_no_rule_defined_is_a_no_op(): void
    {
        $this->stock('1');

        $this->assertSame(0, ReplenishmentSuggestion::count());
    }

    public function test_acknowledging_a_suggestion_marks_it_and_fires_event(): void
    {
        Event::fake();

        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'auto',
        ]);
        $this->stock('1');

        $suggestion = ReplenishmentSuggestion::firstOrFail();
        app(ReorderingService::class)->acknowledge($suggestion);

        $this->assertSame('acknowledged', $suggestion->fresh()->status);
        Event::assertDispatched(ReplenishmentAcknowledged::class);
    }
}
