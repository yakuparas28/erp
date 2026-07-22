<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class InvoiceServiceTest extends TenantTestCase
{
    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    /**
     * @return array{0: PurchaseOrder, 1: PurchaseOrderLine}
     */
    private function purchaseOrderWithLine(string $billControlPolicy, string $qty): array
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $supplier->id,
            'bill_control_policy' => $billControlPolicy,
        ]);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);

        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'uom_id' => $unit->id,
            'qty' => $qty,
        ]);

        return [$po, $line];
    }

    private function receiveStock(PurchaseOrderLine $line, string $qty): void
    {
        $move = new StockMove([
            'product_id' => $line->product_id,
            'from_location_id' => null,
            'to_location_id' => null,
            'uom_id' => $line->uom_id,
            'qty' => $qty,
            'reference_type' => 'purchase_order_line',
            'reference_id' => $line->id,
        ]);
        $move->tenant_id = $this->tenant->id;
        $move->save();
    }

    public function test_create_makes_a_draft_sale_invoice_associated_with_the_sales_order(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);

        $this->assertSame('draft', $invoice->status);
        $this->assertSame('sale', $invoice->type);
        $this->assertTrue($invoice->source->is($salesOrder));
    }

    public function test_sales_invoice_lines_have_no_quantity_limit(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $line = $this->service()->addLine($invoice, $product->id, '99999', '10.0000', null);

        $this->assertSame('99999.0000', $line->qty);
    }

    public function test_purchase_invoice_line_exceeding_received_qty_fails(): void
    {
        [$po, $line] = $this->purchaseOrderWithLine('received_qty', '10');
        $this->receiveStock($line, '5');

        $invoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);

        try {
            $this->service()->addLine($invoice, $line->product_id, '6', '2.0000', null);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('invoice_lines', 0);
    }

    public function test_purchase_invoice_line_matching_received_qty_succeeds(): void
    {
        [$po, $line] = $this->purchaseOrderWithLine('received_qty', '10');
        $this->receiveStock($line, '10');

        $invoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        $invoiceLine = $this->service()->addLine($invoice, $line->product_id, '10', '2.0000', null);

        $this->assertSame('10.0000', $invoiceLine->qty);
    }

    public function test_purchase_invoice_ordered_qty_policy_allows_full_order_qty_without_receipt(): void
    {
        [$po, $line] = $this->purchaseOrderWithLine('ordered_qty', '8');

        $invoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        $invoiceLine = $this->service()->addLine($invoice, $line->product_id, '8', '3.0000', null);

        $this->assertSame('0.0000', $line->receivedQty());
        $this->assertSame('8.0000', $invoiceLine->qty);
    }

    public function test_purchase_invoice_cumulative_quantity_across_invoices_cannot_exceed_received_qty(): void
    {
        [$po, $line] = $this->purchaseOrderWithLine('received_qty', '10');
        $this->receiveStock($line, '10');

        $firstInvoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        $this->service()->addLine($firstInvoice, $line->product_id, '6', '2.0000', null);

        $secondInvoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);

        try {
            $this->service()->addLine($secondInvoice, $line->product_id, '5', '2.0000', null);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('invoice_lines', 1);
    }

    public function test_purchase_invoice_rejects_a_product_not_on_the_purchase_order(): void
    {
        [$po] = $this->purchaseOrderWithLine('received_qty', '10');
        $otherProduct = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $invoice = $this->service()->create($this->tenant->id, $po->partner_id, 'purchase', $po);

        try {
            $this->service()->addLine($invoice, $otherProduct->id, '1', '2.0000', null);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
