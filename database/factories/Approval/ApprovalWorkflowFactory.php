<?php

namespace Database\Factories\Approval;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'subject_type' => 'leave_request',
            'is_active' => true,
        ];
    }
}
