<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Services\Approval\ApprovalService;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class InvoiceApprovalThresholdTest extends TenantTestCase
{
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        ApprovalService::resetResolvers();
        $this->actingAs($this->tenantAdmin);

        setPermissionsTeamId($this->tenant->id);
        $this->accountant = User::factory()->for($this->tenant)->create();
        $this->accountant->assignRole('Accountant');
    }

    public function test_below_threshold_invoice_can_be_posted_without_approval(): void
    {
        $this->tenant->update(['invoice_approval_threshold' => '1000']);

        $invoice = $this->buildInvoice('10', '5.0000');

        app(InvoiceService::class)->post($invoice, $this->accountant);

        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_above_threshold_invoice_refuses_direct_post(): void
    {
        $this->tenant->update(['invoice_approval_threshold' => '10']);
        $invoice = $this->buildInvoice('10', '5.0000');

        $this->expectException(HttpException::class);
        app(InvoiceService::class)->post($invoice, $this->accountant);
    }

    public function test_above_threshold_invoice_can_be_submitted_approved_and_posted(): void
    {
        $this->tenant->update(['invoice_approval_threshold' => '10']);
        $invoice = $this->buildInvoice('10', '5.0000');

        app(InvoiceService::class)->submitForApproval($invoice, $this->accountant);
        $this->assertTrue($invoice->fresh()->isPendingApproval());

        app(ApprovalService::class)->approve($invoice->fresh()->approval, $this->tenantAdmin);
        $this->assertTrue($invoice->fresh()->isApproved());

        app(InvoiceService::class)->post($invoice->fresh(), $this->accountant);
        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_null_threshold_disables_the_workflow(): void
    {
        $this->tenant->update(['invoice_approval_threshold' => null]);
        $invoice = $this->buildInvoice('10', '5.0000');

        app(InvoiceService::class)->post($invoice, $this->accountant);
        $this->assertSame('posted', $invoice->fresh()->status);
    }

    private function buildInvoice(string $qty, string $unitPrice): Invoice
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);

        setPermissionsTeamId($this->tenant->id);
        $officer = User::factory()->for($this->tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $svc = app(PurchaseOrderService::class);
        $po = $svc->create($this->tenant->id, $supplier->id, $officer, 'ordered_qty');
        $svc->addLine($po, $product->id, $unit->id, $qty, $unitPrice);
        $svc->sendRfq($po);
        $svc->confirm($po->fresh(), $this->tenantAdmin);

        $tax = TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();

        $invoice = app(InvoiceService::class)->create($this->tenant->id, $supplier->id, 'purchase', $po->fresh());
        app(InvoiceService::class)->addLine($invoice, $product->id, $qty, $unitPrice, $tax->id);

        return $invoice->fresh();
    }
}
