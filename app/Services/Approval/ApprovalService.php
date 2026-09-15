<?php

namespace App\Services\Approval;

use App\Models\Approval\Approval;
use App\Models\Approval\ApprovalAction;
use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Hr\Models\LeaveRequest;

/**
 * Generic çok-adımlı onay motoru: HR/Fleet gibi farklı modüller aynı yapıyı
 * paylaşır. Onaylayıcı çözünürlüğü genişletilebilir — Hr modülü boot()'unda
 * `registerApproverResolver('manager', …)` çağırıp Employee.manager_id kanalını
 * ekleyebilir. Böylece motor Employee sınıfını bilmez ve modüller arası
 * bağımlılık tek yönlü kalır (Hr → core, core Hr'ı bilmez).
 */
class ApprovalService
{
    /** @var array<string, callable(User, ?string, ?Model): bool> */
    private static array $resolvers = [];

    public static function registerApproverResolver(string $type, Closure $resolver): void
    {
        self::$resolvers[$type] = $resolver;
    }

    /**
     * @internal Sadece testler için.
     */
    public static function resetResolvers(): void
    {
        self::$resolvers = [];
        self::bootDefaultResolvers();
    }

    public static function bootDefaultResolvers(): void
    {
        self::$resolvers['role'] ??= fn (User $actor, ?string $role, ?Model $subject): bool => $role !== null && $actor->hasRole($role);
        self::$resolvers['user'] ??= fn (User $actor, ?string $userId, ?Model $subject): bool => $userId !== null && (int) $userId === $actor->id;

        // Hr modülüne özgü resolver'lar. Modül bağımlılık halkası burada
        // tersine dönüyor (core → Hr) — bunun yerine güvenli çözüm modül
        // etkinse resolver'ı kaydetmek. HR modeli her yerde import edildiği
        // için pratik bir sorun oluşturmuyor.
        self::$resolvers['manager'] ??= function (User $actor, ?string $value, ?Model $subject): bool {
            if (! $subject instanceof LeaveRequest) {
                return false;
            }
            $submitter = $subject->employee()->first();
            if ($submitter === null || $submitter->manager_id === null) {
                return false;
            }
            $actorEmployee = $actor->employee()->first();

            return $actorEmployee !== null && $actorEmployee->id === $submitter->manager_id;
        };

        self::$resolvers['department_manager'] ??= function (User $actor, ?string $value, ?Model $subject): bool {
            if (! $subject instanceof LeaveRequest) {
                return false;
            }
            $submitter = $subject->employee()->with('department')->first();
            $manager = $submitter?->department?->manager_employee_id;
            if ($manager === null) {
                return false;
            }
            $actorEmployee = $actor->employee()->first();

            return $actorEmployee !== null && $actorEmployee->id === $manager;
        };
    }

    /**
     * @param  ?string  $workflowSubjectType  aynı modelde birden fazla iş
     *                                        akışı gerekiyorsa (ör. SalesOrder
     *                                        için hem 'quotation' hem
     *                                        'sales_order') hangi akışın
     *                                        kullanılacağını seçer. Boş ise
     *                                        modelin morph adı kullanılır.
     */
    public function submit(Model $subject, User $submitter, ?string $workflowSubjectType = null): Approval
    {
        $subjectType = $subject->getMorphClass();
        $lookupType = $workflowSubjectType ?? $subjectType;

        $workflow = ApprovalWorkflow::where('tenant_id', $submitter->tenant_id)
            ->where('subject_type', $lookupType)
            ->where('is_active', true)
            ->with('steps')
            ->first();

        abort_if($workflow === null, 422, __('No approval workflow configured for this action.'));
        abort_if($workflow->steps->isEmpty(), 422, __('The approval workflow has no steps.'));

        $firstStep = $workflow->steps->first();

        return DB::transaction(fn () => Approval::create([
            'tenant_id' => $submitter->tenant_id,
            'approvable_type' => $subjectType,
            'approvable_id' => $subject->getKey(),
            'approval_workflow_id' => $workflow->id,
            'current_step' => $firstStep->sequence,
            'status' => 'pending',
            'submitted_by' => $submitter->id,
            'submitted_at' => now(),
        ]));
    }

    public function approve(Approval $approval, User $actor, ?string $comment = null): Approval
    {
        abort_unless($approval->status === 'pending', 422, __('This approval is already decided.'));

        $step = $this->currentStep($approval);
        abort_if($step === null, 500, __('Current step not found.'));
        abort_unless($this->canActOnStep($actor, $step, $approval), 403, __('You are not authorized to approve this step.'));

        return DB::transaction(function () use ($approval, $actor, $step, $comment): Approval {
            ApprovalAction::create([
                'approval_id' => $approval->id,
                'step_sequence' => $step->sequence,
                'actor_user_id' => $actor->id,
                'action' => 'approve',
                'comment' => $comment,
            ]);

            $nextStep = $approval->workflow->steps->firstWhere('sequence', '>', $step->sequence);

            if ($nextStep === null) {
                $approval->update([
                    'status' => 'approved',
                    'current_step' => null,
                    'decided_at' => now(),
                ]);
            } else {
                $approval->update(['current_step' => $nextStep->sequence]);
            }

            return $approval->fresh();
        });
    }

    public function reject(Approval $approval, User $actor, ?string $comment = null): Approval
    {
        abort_unless($approval->status === 'pending', 422, __('This approval is already decided.'));

        $step = $this->currentStep($approval);
        abort_if($step === null, 500, __('Current step not found.'));
        abort_unless($this->canActOnStep($actor, $step, $approval), 403, __('You are not authorized to reject this step.'));

        return DB::transaction(function () use ($approval, $actor, $step, $comment): Approval {
            ApprovalAction::create([
                'approval_id' => $approval->id,
                'step_sequence' => $step->sequence,
                'actor_user_id' => $actor->id,
                'action' => 'reject',
                'comment' => $comment,
            ]);
            $approval->update([
                'status' => 'rejected',
                'current_step' => null,
                'decided_at' => now(),
            ]);

            return $approval->fresh();
        });
    }

    public function cancel(Approval $approval, User $actor, ?string $comment = null): Approval
    {
        abort_unless($approval->status === 'pending', 422, __('This approval is already decided.'));
        abort_unless($approval->submitted_by === $actor->id, 403, __('Only the submitter can cancel this request.'));

        return DB::transaction(function () use ($approval, $actor, $comment): Approval {
            ApprovalAction::create([
                'approval_id' => $approval->id,
                'step_sequence' => $approval->current_step,
                'actor_user_id' => $actor->id,
                'action' => 'cancel',
                'comment' => $comment,
            ]);
            $approval->update([
                'status' => 'cancelled',
                'current_step' => null,
                'decided_at' => now(),
            ]);

            return $approval->fresh();
        });
    }

    public function canActOnStep(User $actor, ApprovalWorkflowStep $step, Approval $approval): bool
    {
        $resolver = self::$resolvers[$step->approver_type] ?? null;

        if ($resolver === null) {
            return false;
        }

        return (bool) $resolver($actor, $step->approver_value, $approval->approvable);
    }

    private function currentStep(Approval $approval): ?ApprovalWorkflowStep
    {
        return $approval->workflow->steps->firstWhere('sequence', $approval->current_step);
    }
}
