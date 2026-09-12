<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = now()->addDays(7);

        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'total_days' => 3,
            'reason' => 'Örnek talep',
        ];
    }
}
