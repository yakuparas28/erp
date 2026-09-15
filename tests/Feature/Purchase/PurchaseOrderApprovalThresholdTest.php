<?php

namespace Tests\Feature\Purchase;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PurchaseOrderApprovalThresholdTest extends TenantTestCase
{
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        ApprovalService::resetResolvers();
        $this->actingAs($this->tenantAdmin);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Purchasing Officer');

        $wf = ApprovalWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PO onayı',
            'subject_type' => 'purchase_order',
            'is_active' => true,
        ]);
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $wf->id,
            'sequence' => 1,
            'approver_type' => 'role',
            'approver_value' => 'Tenant Admin',
        ]);
    }

    public function test_below_threshold_purchase_order_confirms_directly(): void
    {
        $this->tenant->update(['purchase_order_approval_threshold' => '10000']);
        $po = $this->rfqOrder('1', '10');

        app(PurchaseOrderService::class)->confirm($po, $this->tenantAdmin);
        $this->assertSame('confirmed', $po->fresh()->status);
    }

    public function test_above_threshold_purchase_order_refuses_confirm_without_approval(): void
    {
        $this->tenant->update(['purchase_order_approval_threshold' => '5']);
        $po = $this->rfqOrder('1', '10');

        $this->expectException(HttpException::class);
        app(PurchaseOrderService::class)->confirm($po, $this->tenantAdmin);
    }

    public function test_above_threshold_purchase_order_can_be_submitted_approved_and_confirmed(): void
    {
        $this->tenant->update(['purchase_order_approval_threshold' => '5']);
        $po = $this->rfqOrder('1', '10');

        app(PurchaseOrderService::class)->submitForConfirmationApproval($po, $this->officer);
        $this->assertTrue($po->fresh()->isPendingApprovalFor('purchase_order'));

        app(ApprovalService::class)->approve($po->fresh()->approvalFor('purchase_order'), $this->tenantAdmin);

        app(PurchaseOrderService::class)->confirm($po->fresh(), $this->tenantAdmin);
        $this->assertSame('confirmed', $po->fresh()->status);
    }

    private function rfqOrder(string $qty, string $unitPrice): PurchaseOrder
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'cost_method' => 'fifo']);

        $svc = app(PurchaseOrderService::class);
        $po = $svc->create($this->tenant->id, $supplier->id, $this->officer, 'ordered_qty');
        $svc->addLine($po, $product->id, $unit->id, $qty, $unitPrice);
        $svc->sendRfq($po->fresh());

        return $po->fresh();
    }
}
