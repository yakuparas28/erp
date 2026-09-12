<?php

namespace App\Models\Approval;

use Database\Factories\Approval\ApprovalWorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    /** @use HasFactory<ApprovalWorkflowStepFactory> */
    use HasFactory;

    protected $fillable = ['approval_workflow_id', 'sequence', 'approver_type', 'approver_value'];

    protected $casts = ['sequence' => 'integer'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
