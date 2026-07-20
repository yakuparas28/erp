<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PurchaseOrderLifecycleTest extends TenantTestCase
{
    private Partner $supplier;

    private Product $product;

    private Uom $unit;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Purchasing Officer');
    }

    private function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    public function test_full_lifecycle_draft_to_confirmed(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '20', '5.0000');
        $this->service()->sendRfq($po);

        $this->assertSame('rfq_sent', $po->fresh()->status);

        $this->service()->confirm($po->fresh(), $this->tenantAdmin);

        $this->assertSame('confirmed', $po->fresh()->status);
    }

    public function test_creator_cannot_confirm_own_purchase_order(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->tenantAdmin);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendRfq($po);

        try {
            $this->service()->confirm($po->fresh(), $this->tenantAdmin);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_user_without_confirm_permission_cannot_confirm_someone_elses_order(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendRfq($po);

        setPermissionsTeamId($this->tenant->id);
        $otherOfficer = User::factory()->for($this->tenant)->create();
        $otherOfficer->assignRole('Purchasing Officer');

        try {
            $this->service()->confirm($po->fresh(), $otherOfficer);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_draft_purchase_order_cannot_be_confirmed_directly(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '10', '5.0000');

        try {
            $this->service()->confirm($po->fresh(), $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_already_confirmed_order_cannot_be_confirmed_again(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendRfq($po);
        $this->service()->confirm($po->fresh(), $this->tenantAdmin);

        try {
            $this->service()->confirm($po->fresh(), $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
