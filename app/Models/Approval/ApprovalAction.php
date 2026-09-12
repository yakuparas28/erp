<?php

namespace App\Models\Approval;

use App\Models\User;
use Database\Factories\Approval\ApprovalActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    /** @use HasFactory<ApprovalActionFactory> */
    use HasFactory;

    protected $fillable = ['approval_id', 'step_sequence', 'actor_user_id', 'action', 'comment'];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
