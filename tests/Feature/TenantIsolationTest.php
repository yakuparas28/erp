<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class TenantIsolationTest extends TenantTestCase
{
    public function test_tenant_admin_is_provisioned_with_role(): void
    {
        setPermissionsTeamId($this->tenant->id);

        $this->assertTrue($this->tenantAdmin->hasRole('Tenant Admin'));
    }

    public function test_acting_as_tenant_user_authenticates_in_given_tenant(): void
    {
        $user = $this->actingAsTenantUser();

        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('Warehouse Operator'));
    }

    public function test_users_of_another_tenant_are_separate(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        $this->assertNotSame($otherUser->tenant_id, $this->tenantAdmin->tenant_id);
        $this->assertSame(1, User::where('tenant_id', $this->tenant->id)->count());
    }
}
