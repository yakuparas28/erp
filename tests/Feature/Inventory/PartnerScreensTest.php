<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Partner;
use Tests\TenantTestCase;

class PartnerScreensTest extends TenantTestCase
{
    public function test_partners_page_lists_partners(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Test Partneri']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/partners')
            ->assertOk()
            ->assertSee('Test Partneri');
    }

    public function test_new_partner_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/partners', [
            'name' => 'Yeni Partner',
            'tax_number' => '1234567890',
            'is_customer' => '1',
            'payment_term_days' => 15,
        ])->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'name' => 'Yeni Partner',
            'tenant_id' => $this->tenant->id,
            'is_customer' => true,
            'is_supplier' => false,
            'payment_term_days' => 15,
        ]);
    }

    public function test_partner_can_be_updated(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Eski Ad']);

        $this->actingAs($this->tenantAdmin)->put("/app/inventory/partners/{$partner->id}", [
            'name' => 'Güncellenmiş Ad',
            'is_supplier' => '1',
            'payment_term_days' => 45,
        ])->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'name' => 'Güncellenmiş Ad',
            'is_customer' => false,
            'is_supplier' => true,
            'payment_term_days' => 45,
        ]);
    }

    public function test_user_without_permission_cannot_access_partners(): void
    {
        $operator = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/partners')->assertForbidden();
    }
}
