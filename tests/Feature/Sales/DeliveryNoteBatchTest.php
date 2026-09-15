<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class DeliveryNoteBatchTest extends TenantTestCase
{
    private User $officer;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->tenantAdmin);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Tenant Admin');

        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->warehouse = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id]);
    }

    public function test_deliver_many_creates_a_single_delivery_note_with_all_selected_lines(): void
    {
        $so = $this->buildConfirmedOrderWith2Lines();
        $lines = $so->lines()->orderBy('id')->get();

        $note = app(SalesOrderService::class)->deliverMany($so, [
            ['line_id' => $lines[0]->id, 'qty' => '3'],
            ['line_id' => $lines[1]->id, 'qty' => '5'],
        ], [
            'driver_name' => 'Ahmet Yılmaz',
            'vehicle_plate' => '34 ABC 1234',
            'notes' => 'Test sevk',
        ]);

        $this->assertInstanceOf(DeliveryNote::class, $note);
        $this->assertSame('Ahmet Yılmaz', $note->driver_name);
        $this->assertSame('34 ABC 1234', $note->vehicle_plate);
        $this->assertSame('Test sevk', $note->notes);
        $this->assertMatchesRegularExpression('/^IRS-\d{4}-\d{6}$/', $note->note_no);
        $this->assertSame(2, DeliveryNoteLine::where('delivery_note_id', $note->id)->count());

        // Only ONE delivery note was created for the two lines
        $this->assertSame(1, DeliveryNote::where('sales_order_id', $so->id)->count());
    }

    public function test_deliver_many_rejects_a_line_that_belongs_to_a_different_order(): void
    {
        $so = $this->buildConfirmedOrderWith2Lines();
        $foreignOrder = $this->buildConfirmedOrderWith2Lines();
        $foreignLine = $foreignOrder->lines()->firstOrFail();

        $this->expectException(HttpException::class);
        app(SalesOrderService::class)->deliverMany($so, [
            ['line_id' => $foreignLine->id, 'qty' => '1'],
        ], []);
    }

    public function test_deliver_many_rolls_back_when_a_line_exceeds_the_remaining_quantity(): void
    {
        $so = $this->buildConfirmedOrderWith2Lines();
        $lines = $so->lines()->orderBy('id')->get();
        $noteCountBefore = DeliveryNote::where('sales_order_id', $so->id)->count();

        try {
            app(SalesOrderService::class)->deliverMany($so, [
                ['line_id' => $lines[0]->id, 'qty' => '3'],
                ['line_id' => $lines[1]->id, 'qty' => '9999'], // beyond ordered qty
            ], []);
            $this->fail('Expected HttpException.');
        } catch (HttpException $e) {
            // expected
        }

        // Header must not survive the failed batch
        $this->assertSame($noteCountBefore, DeliveryNote::where('sales_order_id', $so->id)->count());
    }

    private function buildConfirmedOrderWith2Lines(): SalesOrder
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'product_type' => 'service', 'cost_method' => 'standard']);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'product_type' => 'service', 'cost_method' => 'standard']);

        $svc = app(SalesOrderService::class);
        $so = $svc->create($this->tenant->id, $customer->id, $this->warehouse->id, $this->officer);
        $svc->addLine($so, $product1->id, $unit->id, '3', '10');
        $svc->addLine($so, $product2->id, $unit->id, '5', '20');
        $svc->sendQuotation($so->fresh(), now()->addDays(7)->toDateString());
        $svc->confirm($so->fresh(), $this->tenantAdmin);

        return $so->fresh();
    }
}
