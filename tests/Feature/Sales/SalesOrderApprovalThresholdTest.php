<?php

namespace Tests\Feature\Sales;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class SalesOrderApprovalThresholdTest extends TenantTestCase
{
    private User $officer;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        ApprovalService::resetResolvers();
        $this->actingAs($this->tenantAdmin);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Tenant Admin');

        $wh = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->warehouse = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id]);

        $this->makeWorkflow('quotation');
        $this->makeWorkflow('sales_order');
    }

    public function test_below_threshold_quotation_can_be_sent_directly(): void
    {
        $this->tenant->update(['quotation_approval_threshold' => '10000']);
        $so = $this->draftOrder('1', '10');

        app(SalesOrderService::class)->sendQuotation($so, now()->addDays(7)->toDateString());

        $this->assertSame('quotation_sent', $so->fresh()->status);
    }

    public function test_above_threshold_quotation_refuses_send_without_approval(): void
    {
        $this->tenant->update(['quotation_approval_threshold' => '5']);
        $so = $this->draftOrder('1', '10');

        $this->expectException(HttpException::class);
        app(SalesOrderService::class)->sendQuotation($so, now()->addDays(7)->toDateString());
    }

    public function test_above_threshold_quotation_can_be_submitted_approved_and_sent(): void
    {
        $this->tenant->update(['quotation_approval_threshold' => '5']);
        $so = $this->draftOrder('1', '10');

        app(SalesOrderService::class)->submitForQuotationApproval($so, $this->officer);
        $this->assertTrue($so->fresh()->isPendingApprovalFor('quotation'));

        app(ApprovalService::class)->approve($so->fresh()->approvalFor('quotation'), $this->tenantAdmin);

        app(SalesOrderService::class)->sendQuotation($so->fresh(), now()->addDays(7)->toDateString());
        $this->assertSame('quotation_sent', $so->fresh()->status);
    }

    public function test_above_threshold_sales_order_refuses_confirm_without_approval(): void
    {
        $this->tenant->update(['sales_order_approval_threshold' => '5']);
        $so = $this->draftOrder('1', '10');
        app(SalesOrderService::class)->sendQuotation($so, now()->addDays(7)->toDateString());

        $this->expectException(HttpException::class);
        app(SalesOrderService::class)->confirm($so->fresh(), $this->tenantAdmin);
    }

    public function test_null_threshold_disables_the_workflow_entirely(): void
    {
        $this->tenant->update([
            'quotation_approval_threshold' => null,
            'sales_order_approval_threshold' => null,
        ]);
        $so = $this->draftOrder('1', '99999');

        app(SalesOrderService::class)->sendQuotation($so, now()->addDays(7)->toDateString());
        app(SalesOrderService::class)->confirm($so->fresh(), $this->tenantAdmin);

        $this->assertSame('confirmed', $so->fresh()->status);
    }

    private function draftOrder(string $qty, string $unitPrice): SalesOrder
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'product_type' => 'service', 'cost_method' => 'standard']);

        $svc = app(SalesOrderService::class);
        $so = $svc->create($this->tenant->id, $customer->id, $this->warehouse->id, $this->officer);
        $svc->addLine($so, $product->id, $unit->id, $qty, $unitPrice);

        return $so->fresh();
    }

    private function makeWorkflow(string $subjectType): void
    {
        $wf = ApprovalWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $subjectType,
            'subject_type' => $subjectType,
            'is_active' => true,
        ]);
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $wf->id,
            'sequence' => 1,
            'approver_type' => 'role',
            'approver_value' => 'Tenant Admin',
        ]);
    }
}
