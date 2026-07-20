<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Modules\Inventory\Models\Partner;
use Tests\TenantTestCase;

class PartnerTest extends TenantTestCase
{
    public function test_a_partner_can_be_a_supplier_a_customer_or_both(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tedarikçi AŞ']);
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id, 'name' => 'Müşteri Ltd']);
        $both = Partner::factory()->create(['tenant_id' => $this->tenant->id, 'is_customer' => true, 'is_supplier' => true]);

        $this->assertTrue($supplier->is_supplier);
        $this->assertFalse($supplier->is_customer);
        $this->assertTrue($customer->is_customer);
        $this->assertFalse($customer->is_supplier);
        $this->assertTrue($both->is_customer && $both->is_supplier);
    }

    public function test_purchasing_officer_role_has_create_purchase_orders_and_manage_partners(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $officer = User::factory()->for($this->tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $this->assertTrue($officer->can('create purchase orders'));
        $this->assertTrue($officer->can('manage partners'));
        $this->assertFalse($officer->can('confirm purchase orders'));
    }
}
