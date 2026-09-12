<?php

namespace App\Models\Approval;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Approval\ApprovalWorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    /** @use HasFactory<ApprovalWorkflowFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'subject_type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('sequence');
    }
}
