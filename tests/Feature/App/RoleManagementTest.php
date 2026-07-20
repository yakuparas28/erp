<?php

namespace Tests\Feature\App;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('Tenant Admin');
    }

    public function test_roles_page_lists_system_and_custom_roles(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/app/roles')
            ->assertOk()
            ->assertSee('Tenant Admin')
            ->assertSee('Warehouse Operator');
    }

    public function test_custom_role_can_be_created_for_the_tenant(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/app/roles', ['name' => 'Depo Şefi'])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'Depo Şefi', 'tenant_id' => $this->tenant->id]);
    }

    public function test_custom_role_permissions_can_be_synced(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $role = Role::create(['name' => 'Depo Şefi', 'guard_name' => 'web']);

        $this->actingAs($this->admin, 'web')
            ->put("/app/roles/{$role->id}/permissions", [
                'permissions' => ['view stock', 'approve inventory adjustments'],
            ])->assertRedirect();

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($role->fresh()->hasPermissionTo('approve inventory adjustments'));
        $this->assertFalse($role->fresh()->hasPermissionTo('manage users'));
    }

    public function test_system_role_cannot_be_modified_or_deleted(): void
    {
        $systemRole = Role::whereNull('tenant_id')->where('name', 'Tenant Admin')->firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->put("/app/roles/{$systemRole->id}", ['name' => 'Hacklendi'])
            ->assertForbidden();

        $this->actingAs($this->admin, 'web')
            ->put("/app/roles/{$systemRole->id}/permissions", ['permissions' => []])
            ->assertForbidden();

        $this->actingAs($this->admin, 'web')
            ->delete("/app/roles/{$systemRole->id}")
            ->assertForbidden();
    }

    public function test_custom_role_with_users_cannot_be_deleted(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $role = Role::create(['name' => 'Depo Şefi', 'guard_name' => 'web']);
        User::factory()->for($this->tenant)->create()->assignRole($role);

        $this->actingAs($this->admin, 'web')
            ->from('/app/roles')
            ->delete("/app/roles/{$role->id}")
            ->assertRedirect('/app/roles')
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_another_tenants_custom_role_is_not_accessible(): void
    {
        $otherTenant = Tenant::factory()->create();
        setPermissionsTeamId($otherTenant->id);
        $foreignRole = Role::create(['name' => 'Yabancı Rol', 'guard_name' => 'web']);

        $this->actingAs($this->admin, 'web')
            ->put("/app/roles/{$foreignRole->id}", ['name' => 'Ele Geçirildi'])
            ->assertNotFound();
    }

    public function test_user_without_manage_roles_permission_gets_403(): void
    {
        $operator = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator, 'web')->get('/app/roles')->assertForbidden();
    }
}
