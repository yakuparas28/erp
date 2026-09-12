<?php

namespace App\Models\Approval;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\Approval\ApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'approvable_type', 'approvable_id', 'approval_workflow_id',
        'current_step', 'status', 'submitted_by', 'submitted_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'current_step' => 'integer',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->latest();
    }
}
