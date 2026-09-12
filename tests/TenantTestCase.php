<?php

namespace Tests;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tenant bağlamı gerektiren feature testleri için taban sınıf.
 * setUp bir tenant + Tenant Admin kullanıcısı hazırlar; actingAsTenantUser
 * ile istenen tenant'ta kimlik doğrulanmış kullanıcı üretilir. Modüllerin
 * tümü varsayılan olarak aktive edilir; lisans kısıtlamasını test etmek
 * isteyen sınıflar bu davranışı override edebilir.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModuleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->tenantAdmin = User::factory()->for($this->tenant)->create();

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->assignRole('Tenant Admin');

        $this->activateAllModules($this->tenant);
    }

    /**
     * Her non-core modülü bu tenant için aktive eder. Çekirdek modüller
     * (Envanter) middleware tarafından zaten geçirilir.
     */
    protected function activateAllModules(Tenant $tenant): void
    {
        foreach (Module::where('is_core', false)->get() as $module) {
            TenantModuleActivation::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                ['is_active' => true, 'source' => 'trial'],
            );
        }
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
