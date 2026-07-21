<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_default_roles_are_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'Tenant Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'Warehouse Operator']);
    }

    public function test_a_role_assigned_in_one_tenant_does_not_leak_to_another(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->for($tenantA)->create();

        setPermissionsTeamId($tenantA->id);
        $user->assignRole('Tenant Admin');

        $this->assertTrue($user->hasRole('Tenant Admin'));

        setPermissionsTeamId($tenantB->id);
        $user->unsetRelation('roles');

        $this->assertFalse($user->hasRole('Tenant Admin'));
    }

    public function test_middleware_sets_team_id_from_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)->getJson('/api/user');

        $this->assertSame($tenant->id, getPermissionsTeamId());
    }

    public function test_sales_representative_has_create_and_partner_permissions_but_not_confirm(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        setPermissionsTeamId($tenant->id);
        $user->assignRole('Sales Representative');

        $this->assertTrue($user->hasPermissionTo('create sales orders'));
        $this->assertTrue($user->hasPermissionTo('manage partners'));
        $this->assertFalse($user->hasPermissionTo('confirm sales orders'));
    }
}
