<?php

namespace App\Concerns;

use App\Models\Approval\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Onay motoruna konu olan modeller için (leave_request, vehicle_reservation
 * vb.) tek yönlü kısayol. Aynı model üzerinde birden fazla iş akışı
 * mümkün olduğu için (ör. SalesOrder → "quotation" ve "sales_order"
 * subject_type'ları), workflow'a özel sorgulama helper'ları da vardır.
 */
trait HasApproval
{
    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable')->latestOfMany();
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function approvalFor(string $workflowSubjectType): ?Approval
    {
        return $this->approvals()
            ->whereHas('workflow', fn ($q) => $q->where('subject_type', $workflowSubjectType))
            ->latest('id')
            ->first();
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

    public function isApprovedFor(string $workflowSubjectType): bool
    {
        return $this->approvalFor($workflowSubjectType)?->status === 'approved';
    }

    public function isPendingApprovalFor(string $workflowSubjectType): bool
    {
        return $this->approvalFor($workflowSubjectType)?->status === 'pending';
    }
}
