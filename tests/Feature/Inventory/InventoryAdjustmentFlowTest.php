<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryAdjustmentService;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class InventoryAdjustmentFlowTest extends TenantTestCase
{
    private Location $location;

    private Product $product;

    private Uom $unit;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        // Onaylayacak ikinci admin (görev ayrılığı testi için)
        $this->approver = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->approver->assignRole('Tenant Admin');

        // Başlangıç stoğu: 10
        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 990,
        );
    }

    private function service(): InventoryAdjustmentService
    {
        return app(InventoryAdjustmentService::class);
    }

    public function test_full_count_cycle_produces_difference_move_once(): void
    {
        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);

        $this->assertTrue($this->location->fresh()->counting_lock);

        // İşçi 7 saydı (teorik 10 → fark −3)
        $this->service()->addCount($adjustment, $this->product->id, '7', null);
        $this->service()->submitForApproval($adjustment);
        $this->service()->approve($adjustment->fresh(), $this->approver);

        $this->assertFalse($this->location->fresh()->counting_lock);
        $this->assertSame('approved', $adjustment->fresh()->status);

        $diffMoves = StockMove::where('reference_type', 'inventory_adjustment')->where('reference_id', $adjustment->id)->get();
        $this->assertCount(1, $diffMoves);
        $this->assertSame('-3.0000', $diffMoves->first()->qty);
        $this->assertSame('7.0000', StockQuant::where('location_id', $this->location->id)->firstOrFail()->qty);

        // İkinci onay denemesi yeni move üretmemeli
        try {
            $this->service()->approve($adjustment->fresh(), $this->approver);
        } catch (HttpException) {
        }
        $this->assertCount(1, StockMove::where('reference_type', 'inventory_adjustment')->where('reference_id', $adjustment->id)->get());
    }

    public function test_counts_merge_additively(): void
    {
        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);

        // İki işçi paralel: 4 + 8 = 12 (teorik 10 → fark +2)
        $this->service()->addCount($adjustment, $this->product->id, '4', null);
        $this->service()->addCount($adjustment, $this->product->id, '8', null);

        $this->assertSame('12.0000', $adjustment->lines()->firstOrFail()->counted_qty);

        $this->service()->submitForApproval($adjustment);
        $this->service()->approve($adjustment->fresh(), $this->approver);

        $this->assertSame('12.0000', StockQuant::where('location_id', $this->location->id)->firstOrFail()->qty);
    }

    public function test_creator_cannot_approve_own_adjustment(): void
    {
        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);
        $this->service()->addCount($adjustment, $this->product->id, '9', null);
        $this->service()->submitForApproval($adjustment);

        try {
            $this->service()->approve($adjustment->fresh(), $this->tenantAdmin);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_approver_needs_permission(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);
        $this->service()->submitForApproval($adjustment);

        try {
            $this->service()->approve($adjustment->fresh(), $operator);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_location_already_under_count_rejects_second_adjustment(): void
    {
        $first = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($first);

        $second = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);

        try {
            $this->service()->startCounting($second);
            $this->fail('409 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_cancelling_releases_the_lock(): void
    {
        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);
        $this->assertTrue($this->location->fresh()->counting_lock);

        $this->service()->cancel($adjustment->fresh());

        $this->assertFalse($this->location->fresh()->counting_lock);
        $this->assertSame('cancelled', $adjustment->fresh()->status);
    }

    public function test_uncounted_existing_product_is_zeroed_on_approval(): void
    {
        $adjustment = $this->service()->open($this->tenant->id, $this->location->id, $this->tenantAdmin);
        $this->service()->startCounting($adjustment);
        // Hiçbir şey sayılmadı ama üründen 10 adet vardı → fark −10
        $this->service()->addCount($adjustment, $this->product->id, '0', null);
        $this->service()->submitForApproval($adjustment);
        $this->service()->approve($adjustment->fresh(), $this->approver);

        $this->assertSame('0.0000', StockQuant::where('location_id', $this->location->id)->firstOrFail()->qty);
    }
}
