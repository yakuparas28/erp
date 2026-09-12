<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

class MultiStepWarehouseTest extends TenantTestCase
{
    private function scaffold(string $receptionSteps, string $deliverySteps): array
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'reception_steps' => $receptionSteps, 'delivery_steps' => $deliverySteps]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Stok']);
        $input = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Alım Bekliyor']);
        $qc = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Kalite Kontrol']);
        $output = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Sevk Bekliyor']);
        $pack = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Paketleme']);
        $warehouse->update([
            'input_location_id' => $input->id,
            'quality_location_id' => $qc->id,
            'output_location_id' => $output->id,
            'pack_location_id' => $pack->id,
        ]);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id, 'track_by' => 'none', 'reservation_method' => 'manual']);

        return compact('warehouse', 'stock', 'input', 'qc', 'output', 'pack', 'uom', 'product');
    }

    public function test_two_step_receipt_routes_through_input_location(): void
    {
        [
            'stock' => $stock, 'input' => $input, 'uom' => $uom, 'product' => $product,
        ] = $this->scaffold('two_step', 'one_step');

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $supplier->id, 'status' => 'confirmed']);
        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id, 'purchase_order_id' => $po->id,
            'product_id' => $product->id, 'uom_id' => $uom->id,
            'qty' => '10', 'unit_price' => '5',
        ]);

        app(PurchaseOrderService::class)->receive($line, '10', $stock->id);

        // Inbound: null->input (+10); Transfer: input->stock (-10, +10)
        $this->assertSame('0.0000', (string) StockQuant::where('location_id', $input->id)->where('product_id', $product->id)->value('qty'));
        $this->assertSame('10.0000', (string) StockQuant::where('location_id', $stock->id)->where('product_id', $product->id)->value('qty'));
    }

    public function test_three_step_receipt_routes_input_qc_stock(): void
    {
        [
            'stock' => $stock, 'input' => $input, 'qc' => $qc, 'uom' => $uom, 'product' => $product,
        ] = $this->scaffold('three_step', 'one_step');

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $supplier->id, 'status' => 'confirmed']);
        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id, 'purchase_order_id' => $po->id,
            'product_id' => $product->id, 'uom_id' => $uom->id,
            'qty' => '5', 'unit_price' => '3',
        ]);

        app(PurchaseOrderService::class)->receive($line, '5', $stock->id);

        $this->assertSame('0.0000', (string) StockQuant::where('location_id', $input->id)->where('product_id', $product->id)->value('qty'));
        $this->assertSame('0.0000', (string) StockQuant::where('location_id', $qc->id)->where('product_id', $product->id)->value('qty'));
        $this->assertSame('5.0000', (string) StockQuant::where('location_id', $stock->id)->where('product_id', $product->id)->value('qty'));
    }

    public function test_two_step_delivery_routes_through_output_location(): void
    {
        [
            'stock' => $stock, 'output' => $output, 'uom' => $uom, 'product' => $product,
        ] = $this->scaffold('one_step', 'two_step');

        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $stock->id, 'qty' => '20']);

        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id, 'partner_id' => $customer->id, 'location_id' => $stock->id,
            'status' => 'confirmed', 'created_by' => $this->tenantAdmin->id,
        ]);
        $line = SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id, 'sales_order_id' => $so->id,
            'product_id' => $product->id, 'uom_id' => $uom->id,
            'qty' => '5', 'unit_price' => '10', 'delivered_qty' => '0', 'reserved_qty' => '0',
        ]);

        app(SalesOrderService::class)->deliver($line, '3');

        // stock: 20 - 3 = 17; output: 0 (goes through); customer (null): +3
        $this->assertSame('17.0000', (string) StockQuant::where('location_id', $stock->id)->where('product_id', $product->id)->value('qty'));
        $this->assertSame('0.0000', (string) StockQuant::where('location_id', $output->id)->where('product_id', $product->id)->value('qty'));
    }

    public function test_one_step_receipt_keeps_original_behavior(): void
    {
        [
            'stock' => $stock, 'uom' => $uom, 'product' => $product,
        ] = $this->scaffold('one_step', 'one_step');

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $supplier->id, 'status' => 'confirmed']);
        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id, 'purchase_order_id' => $po->id,
            'product_id' => $product->id, 'uom_id' => $uom->id,
            'qty' => '7', 'unit_price' => '2',
        ]);

        app(PurchaseOrderService::class)->receive($line, '7', $stock->id);

        $this->assertSame('7.0000', (string) StockQuant::where('location_id', $stock->id)->where('product_id', $product->id)->value('qty'));
        // Only one inbound move
        $this->assertSame(1, StockMove::where('product_id', $product->id)->count());
    }
}
