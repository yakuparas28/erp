<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantModuleActivation>
 */
class TenantModuleActivationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'module_id' => Module::factory(),
            'is_active' => true,
            'source' => 'package',
            'activated_by_super_admin_id' => null,
            'activated_at' => now(),
            'deactivated_at' => null,
        ];
    }

    public function manualAddon(): static
    {
        return $this->state(fn (array $attributes): array => ['source' => 'manual_addon']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
