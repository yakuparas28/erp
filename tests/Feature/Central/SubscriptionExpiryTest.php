<?php

namespace Tests\Feature\Central;

use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);
    }

    private function activeCount(Tenant $tenant): int
    {
        return TenantModuleActivation::where('tenant_id', $tenant->id)->where('is_active', true)->count();
    }

    public function test_cancelling_a_subscription_deactivates_package_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Standart')->firstOrFail()->id,
        ]);

        $this->assertSame(3, $this->activeCount($tenant));

        $subscription->update(['status' => 'cancelled']);

        $this->assertSame(0, $this->activeCount($tenant));
    }

    public function test_expired_subscriptions_are_deactivated_by_the_scheduled_command(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Standart')->firstOrFail()->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(3, $this->activeCount($tenant));

        $this->travel(3)->days();

        $this->artisan('subscriptions:deactivate-expired')->assertSuccessful();

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame(0, $this->activeCount($tenant));
    }

    public function test_manual_addons_survive_expiry(): void
    {
        $tenant = Tenant::factory()->create();
        $accounting = Module::where('key', 'accounting')->firstOrFail();

        TenantModuleActivation::factory()->manualAddon()->create([
            'tenant_id' => $tenant->id,
            'module_id' => $accounting->id,
        ]);

        $subscription = TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Başlangıç')->firstOrFail()->id,
            'ends_at' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('subscriptions:deactivate-expired')->assertSuccessful();

        $this->assertTrue(
            TenantModuleActivation::where('tenant_id', $tenant->id)
                ->where('module_id', $accounting->id)
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function test_reactivating_a_cancelled_subscription_restores_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'license_package_id' => LicensePackage::where('name', 'Standart')->firstOrFail()->id,
            'status' => 'cancelled',
        ]);

        $this->assertSame(0, $this->activeCount($tenant));

        $subscription->update(['status' => 'active', 'ends_at' => now()->addYear()->toDateString()]);

        $this->assertSame(3, $this->activeCount($tenant));
    }
}
