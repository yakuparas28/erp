<?php

namespace Database\Factories\Approval;

use App\Models\Approval\Approval;
use App\Models\Approval\ApprovalWorkflow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalFactory extends Factory
{
    protected $model = Approval::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'approvable_type' => 'leave_request',
            'approvable_id' => 1,
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'current_step' => 1,
            'status' => 'pending',
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ];
    }
}
