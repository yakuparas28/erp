<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\GoodsReceipt;
use Modules\Purchase\Models\GoodsReceiptLine;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class GoodsReceiptBatchTest extends TenantTestCase
{
    private User $officer;

    private Location $dock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->tenantAdmin);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Purchasing Officer');

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
    }

    public function test_receive_many_creates_a_single_goods_receipt_for_all_selected_lines(): void
    {
        $po = $this->buildConfirmedPoWith2Lines();
        $lines = $po->lines()->orderBy('id')->get();

        $receipt = app(PurchaseOrderService::class)->receiveMany($po, [
            ['line_id' => $lines[0]->id, 'qty' => '4'],
            ['line_id' => $lines[1]->id, 'qty' => '6'],
        ], $this->dock->id, [
            'waybill_no' => 'ABC-001',
            'notes' => 'Toplu mal kabul',
        ]);

        $this->assertInstanceOf(GoodsReceipt::class, $receipt);
        $this->assertSame('ABC-001', $receipt->waybill_no);
        $this->assertSame($this->dock->id, $receipt->warehouse_location_id);
        $this->assertMatchesRegularExpression('/^MKF-\d{4}-\d{6}$/', $receipt->receipt_no);
        $this->assertSame(2, GoodsReceiptLine::where('goods_receipt_id', $receipt->id)->count());
        $this->assertSame(1, GoodsReceipt::where('purchase_order_id', $po->id)->count());
    }

    public function test_receive_many_rolls_back_when_a_line_belongs_to_another_order(): void
    {
        $po = $this->buildConfirmedPoWith2Lines();
        $foreign = $this->buildConfirmedPoWith2Lines();
        $foreignLine = $foreign->lines()->firstOrFail();
        $before = GoodsReceipt::where('purchase_order_id', $po->id)->count();

        try {
            app(PurchaseOrderService::class)->receiveMany($po, [
                ['line_id' => $foreignLine->id, 'qty' => '1'],
            ], $this->dock->id, []);
            $this->fail('Expected HttpException.');
        } catch (HttpException) {
            // expected
        }

        // Header must not survive the failed batch
        $this->assertSame($before, GoodsReceipt::where('purchase_order_id', $po->id)->count());
    }

    private function buildConfirmedPoWith2Lines(): PurchaseOrder
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'cost_method' => 'fifo']);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'cost_method' => 'fifo']);

        $svc = app(PurchaseOrderService::class);
        $po = $svc->create($this->tenant->id, $supplier->id, $this->officer, 'ordered_qty');
        $svc->addLine($po, $product1->id, $unit->id, '4', '7.5');
        $svc->addLine($po, $product2->id, $unit->id, '6', '10');
        $svc->sendRfq($po->fresh());
        $svc->confirm($po->fresh(), $this->tenantAdmin);

        return $po->fresh();
    }
}
