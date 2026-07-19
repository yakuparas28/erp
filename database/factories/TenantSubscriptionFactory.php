<?php

namespace Database\Factories;

use App\Models\LicensePackage;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_package_id' => LicensePackage::factory(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
        ];
    }
}
