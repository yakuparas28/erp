<?php

namespace Database\Factories\Approval;

use App\Models\Approval\Approval;
use App\Models\Approval\ApprovalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    public function definition(): array
    {
        return [
            'approval_id' => Approval::factory(),
            'step_sequence' => 1,
            'actor_user_id' => User::factory(),
            'action' => 'approve',
            'comment' => null,
        ];
    }
}
