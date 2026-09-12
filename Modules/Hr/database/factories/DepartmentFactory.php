<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\Department;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Yönetim', 'Muhasebe', 'İK', 'Satış', 'Operasyon']),
            'is_active' => true,
        ];
    }
}
