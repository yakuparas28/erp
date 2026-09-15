<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceMatchingService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Tests\TenantTestCase;

class InvoiceMatchingTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_purchase_invoice_matches_when_po_receipt_and_invoice_amounts_align(): void
    {
        [$po, $product, $unit, $dock, $officer] = $this->buildPo('10', '7.5000');

        $line = $po->lines()->firstOrFail();
        app(PurchaseOrderService::class)->receive($line, '10', $dock->id);

        $invoice = app(InvoiceService::class)->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        app(InvoiceService::class)->addLine($invoice, $product->id, '10', '7.5000', $this->purchaseTaxRate()->id);

        $this->assertSame(Invoice::MATCH_MATCHED, $invoice->fresh()->matching_status);
    }

    public function test_purchase_invoice_reports_mismatch_when_invoice_amount_diverges(): void
    {
        [$po, $product, $unit, $dock, $officer] = $this->buildPo('10', '7.5000');

        $line = $po->lines()->firstOrFail();
        app(PurchaseOrderService::class)->receive($line, '10', $dock->id);

        $invoice = app(InvoiceService::class)->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        // wrong unit price triggers mismatch
        app(InvoiceService::class)->addLine($invoice, $product->id, '10', '9.0000', $this->purchaseTaxRate()->id);

        $this->assertSame(Invoice::MATCH_MISMATCH, $invoice->fresh()->matching_status);
    }

    public function test_purchase_invoice_stays_pending_until_a_goods_receipt_is_recorded(): void
    {
        [$po, $product] = $this->buildPo('10', '7.5000', 'ordered_qty');

        $invoice = app(InvoiceService::class)->create($this->tenant->id, $po->partner_id, 'purchase', $po);
        app(InvoiceService::class)->addLine($invoice, $product->id, '10', '7.5000', $this->purchaseTaxRate()->id);

        $this->assertSame(Invoice::MATCH_PENDING, $invoice->fresh()->matching_status);
    }

    public function test_sales_invoice_is_marked_not_applicable(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $someInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customer->id,
            'type' => 'sale',
            'source_type' => 'sales_order',
            'source_id' => 999,
        ]);

        app(InvoiceMatchingService::class)->evaluate($someInvoice);

        $this->assertSame(Invoice::MATCH_NOT_APPLICABLE, $someInvoice->fresh()->matching_status);
    }

    /** @return array{0: PurchaseOrder, 1: Product, 2: Uom, 3: Location, 4: User} */
    private function buildPo(string $qty, string $unitPrice, string $policy = 'received_qty'): array
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $dock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'cost_method' => 'fifo']);

        setPermissionsTeamId($this->tenant->id);
        $officer = User::factory()->for($this->tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $svc = app(PurchaseOrderService::class);
        $po = $svc->create($this->tenant->id, $supplier->id, $officer, $policy);
        $svc->addLine($po, $product->id, $unit->id, $qty, $unitPrice);
        $svc->sendRfq($po);
        $svc->confirm($po->fresh(), $this->tenantAdmin);

        return [$po->fresh(), $product, $unit, $dock, $officer];
    }

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'purchase')
            ->where('percentage', '20')
            ->firstOrFail();
    }
}
