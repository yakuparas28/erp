<?php

namespace Tests\Feature\Central;

use App\Models\LicensePackage;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\User;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CentralApiTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);
        $this->superAdmin = SuperAdmin::factory()->create();
    }

    private function asSuperAdmin(): static
    {
        return $this->withToken($this->superAdmin->createToken('central')->plainTextToken);
    }

    public function test_super_admin_can_create_a_tenant_and_action_is_logged(): void
    {
        $response = $this->asSuperAdmin()->postJson('/api/central/tenants', [
            'name' => 'Yeni Firma AŞ',
            'accounting_mode' => 'continental',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'Yeni Firma AŞ');

        $activity = Activity::where('description', 'tenant.created')->first();
        $this->assertNotNull($activity);
        $this->assertTrue($activity->causer->is($this->superAdmin));
    }

    public function test_super_admin_can_list_tenants(): void
    {
        Tenant::factory()->count(2)->create();

        $this->asSuperAdmin()->getJson('/api/central/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_assigning_a_subscription_creates_activations_and_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $package = LicensePackage::where('name', 'Standart')->firstOrFail();

        $this->asSuperAdmin()->postJson("/api/central/tenants/{$tenant->id}/subscription", [
            'license_package_id' => $package->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(
            6,
            TenantModuleActivation::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
        );
        $this->assertNotNull(Activity::where('description', 'subscription.assigned')->first());
    }

    public function test_manual_addon_activation_and_core_deactivation_guard(): void
    {
        $tenant = Tenant::factory()->create();

        $this->asSuperAdmin()
            ->postJson("/api/central/tenants/{$tenant->id}/modules/accounting")
            ->assertCreated();

        $this->assertSame(
            'manual_addon',
            TenantModuleActivation::where('tenant_id', $tenant->id)->value('source'),
        );
        $this->assertNotNull(Activity::where('description', 'module.activated')->first());

        $this->asSuperAdmin()
            ->deleteJson("/api/central/tenants/{$tenant->id}/modules/inventory")
            ->assertUnprocessable();
    }

    public function test_tenant_user_cannot_access_central_endpoints(): void
    {
        $user = User::factory()->for(Tenant::factory())->create();

        $this->withToken($user->createToken('api')->plainTextToken)
            ->getJson('/api/central/tenants')
            ->assertUnauthorized();
    }
}
