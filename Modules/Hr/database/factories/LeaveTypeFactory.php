<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\LeaveType;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => 'yillik',
            'name' => 'Yıllık İzin',
            'deducts_from_balance' => true,
            'requires_document' => false,
            'requires_second_level' => false,
            'unit' => 'day',
            'is_active' => true,
        ];
    }
}
