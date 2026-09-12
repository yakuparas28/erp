<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveBalance;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'year' => (int) date('Y'),
            'carried_from_previous' => 0,
            'current_year_entitlement' => 14,
            'manual_adjustment' => 0,
        ];
    }
}
