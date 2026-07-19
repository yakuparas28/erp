<?php

namespace Tests\Feature\App;

use App\Models\LicensePackage;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);
    }

    public function test_dashboard_shows_tenant_modules_with_activation_state(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Panel Firması']);
        TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Standart')->firstOrFail()->id,
        ]);
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user, 'web')
            ->get('/app')
            ->assertOk()
            ->assertSee('Panel Firması')
            ->assertSee('Standart')
            ->assertSee('Envanter')
            ->assertSee('Pakete dahil değil');
    }

    public function test_super_admin_session_cannot_open_tenant_dashboard(): void
    {
        $admin = SuperAdmin::factory()->create();

        $this->actingAs($admin, 'central_web')->get('/app')->assertRedirect('/login');
    }
}
