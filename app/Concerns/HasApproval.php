<?php

namespace App\Concerns;

use App\Models\Approval\Approval;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Onay motoruna konu olan modeller için (leave_request, vehicle_reservation
 * vb.) tek yönlü kısayol. Bir modele en fazla bir aktif Approval kaydı bağlanır;
 * `latestOfMany` yeniden gönderilen istekleri de destekler.
 */
trait HasApproval
{
    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable')->latestOfMany();
    }

    public function approvalStatus(): ?string
    {
        return $this->approval?->status;
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalStatus() === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus() === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approvalStatus() === 'rejected';
    }
}
