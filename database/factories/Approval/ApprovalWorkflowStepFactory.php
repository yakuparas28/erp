<?php

namespace Database\Factories\Approval;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowStepFactory extends Factory
{
    protected $model = ApprovalWorkflowStep::class;

    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'sequence' => 1,
            'approver_type' => 'role',
            'approver_value' => 'Tenant Admin',
        ];
    }
}
