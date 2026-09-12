<?php

namespace Tests\Feature\Central;

use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use App\Services\Platform\ModuleActivationService;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionActivationSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);
    }

    private function activeModuleKeys(Tenant $tenant): array
    {
        return TenantModuleActivation::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->join('modules', 'modules.id', '=', 'tenant_module_activations.module_id')
            ->orderBy('modules.key')
            ->pluck('modules.key')
            ->all();
    }

    public function test_creating_a_subscription_activates_package_modules(): void
    {
        $tenant = Tenant::factory()->create();

        TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Standart')->firstOrFail()->id,
        ]);

        $this->assertSame(['hr', 'inventory', 'purchase', 'sales'], $this->activeModuleKeys($tenant));
        $this->assertSame(
            ['package'],
            TenantModuleActivation::where('tenant_id', $tenant->id)->distinct()->pluck('source')->all(),
        );
    }

    public function test_changing_the_package_activates_new_and_deactivates_removed_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Premium')->firstOrFail()->id,
        ]);

        $subscription->update([
            'license_package_id' => LicensePackage::where('name', 'Başlangıç')->firstOrFail()->id,
        ]);

        $this->assertSame(['inventory'], $this->activeModuleKeys($tenant));

        $accounting = TenantModuleActivation::where('tenant_id', $tenant->id)
            ->where('module_id', Module::where('key', 'accounting')->firstOrFail()->id)
            ->firstOrFail();

        $this->assertFalse($accounting->is_active);
        $this->assertNotNull($accounting->deactivated_at);
    }

    public function test_manual_addons_survive_package_sync(): void
    {
        $tenant = Tenant::factory()->create();
        $accounting = Module::where('key', 'accounting')->firstOrFail();
        $superAdmin = SuperAdmin::factory()->create();

        app(ModuleActivationService::class)->activateManually($tenant, $accounting, $superAdmin);

        TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Başlangıç')->firstOrFail()->id,
        ]);

        $this->assertSame(['accounting', 'inventory'], $this->activeModuleKeys($tenant));
        $this->assertSame(
            'manual_addon',
            TenantModuleActivation::where('tenant_id', $tenant->id)
                ->where('module_id', $accounting->id)
                ->value('source'),
        );
    }

    public function test_core_module_cannot_be_deactivated(): void
    {
        $tenant = Tenant::factory()->create();
        $inventory = Module::where('key', 'inventory')->firstOrFail();
        $superAdmin = SuperAdmin::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(ModuleActivationService::class)->deactivate($tenant, $inventory, $superAdmin);
    }
}
