<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\Employee;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'title' => fake()->jobTitle(),
            'hire_date' => now()->subYears(2)->toDateString(),
            'is_active' => true,
            'annual_leave_balance' => 14,
        ];
    }
}
