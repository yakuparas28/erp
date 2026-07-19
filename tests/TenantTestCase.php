<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tenant bağlamı gerektiren feature testleri için taban sınıf.
 * setUp bir tenant + Tenant Admin kullanıcısı hazırlar; actingAsTenantUser
 * ile istenen tenant'ta kimlik doğrulanmış kullanıcı üretilir.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->tenantAdmin = User::factory()->for($this->tenant)->create();

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->assignRole('Tenant Admin');
    }

    protected function actingAsTenantUser(?Tenant $tenant = null, string $role = 'Warehouse Operator'): User
    {
        $tenant ??= $this->tenant;

        $user = User::factory()->for($tenant)->create();

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }
}
