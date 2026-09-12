<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Approval\Approval;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Services\LeaveRequestService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Onay ekranları. Sadece pending approval'lı LeaveRequest'ler listelenir.
 * Onaylayıcı doğrulaması ApprovalService::canActOnStep tarafından yapılır;
 * bu ekran sadece "bir adımı ben karara bağlayabilir miyim?" filtresi
 * uygulayıp gösterir — motor son karar merci.
 */
class LeaveApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LeaveRequestService $leaves,
    ) {}

    public function firstLevelIndex(Request $request): View
    {
        return view('hr::leaves.approval-first', [
            'requests' => $this->pendingLeaves($request, ['manager', 'department_manager', 'role', 'user']),
        ]);
    }

    public function secondLevelIndex(Request $request): View
    {
        return view('hr::leaves.approval-second', [
            'requests' => $this->pendingLeaves($request, ['role', 'user']),
        ]);
    }

    public function monitoring(): View
    {
        return view('hr::leaves.monitoring', [
            'requests' => LeaveRequest::with(['employee', 'leaveType', 'approval.actions.actor'])
                ->latest()
                ->get(),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        return $this->decide($request, $leave, 'approve');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        return $this->decide($request, $leave, 'reject');
    }

    private function decide(Request $request, LeaveRequest $leave, string $action): RedirectResponse
    {
        $approval = $leave->approval;
        abort_if($approval === null, 422, __('This leave has no active approval.'));

        $comment = $request->input('comment');

        try {
            $method = $action === 'approve' ? 'approve' : 'reject';
            $this->approvals->{$method}($approval, $request->user(), $comment);
            $this->leaves->afterDecision($leave);
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', $action === 'approve' ? __('Leave approved.') : __('Leave rejected.'));
    }

    /**
     * Kullanıcının o an karara bağlayabileceği pending leave'ler.
     */
    private function pendingLeaves(Request $request, array $stepTypes): Collection
    {
        $user = $request->user();

        return LeaveRequest::whereHas('approval', fn ($q) => $q->where('status', 'pending'))
            ->with(['employee.department', 'leaveType', 'approval.workflow.steps'])
            ->get()
            ->filter(function (LeaveRequest $leave) use ($user, $stepTypes) {
                $approval = $leave->approval;
                if ($approval === null) {
                    return false;
                }
                $step = $approval->workflow->steps->firstWhere('sequence', $approval->current_step);
                if ($step === null || ! in_array($step->approver_type, $stepTypes, true)) {
                    return false;
                }

                return $this->approvals->canActOnStep($user, $step, $approval);
            })
            ->values();
    }
}
