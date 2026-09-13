<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;
use Modules\Hr\Services\LeaveNotificationService;
use Modules\Hr\Services\LeaveRequestService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Personel için "Taleplerim" ekranı: kendi izin taleplerini görür, yeni talep
 * gönderir, bekleyen talebini iptal eder. Tüm işlemler kullanıcının HR
 * profiline (Employee) bağlıdır; HR profili olmayan user 403 alır.
 */
class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    public function index(Request $request): View
    {
        $employee = $this->currentEmployee($request);

        return view('hr::leaves.mine', [
            'requests' => LeaveRequest::where('employee_id', $employee->id)
                ->with(['leaveType', 'approval'])
                ->latest()->get(),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
            'employee' => $employee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);
        $tenantId = $employee->tenant_id;

        $validated = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day_type' => ['nullable', Rule::in(['morning', 'afternoon'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'travel_allowance_requested' => ['sometimes', 'boolean'],
            'ticket_no' => ['nullable', 'string', 'max:50'],
            'return_to_work_date' => ['nullable', 'date', 'after:end_date'],
        ]);

        try {
            $this->service->submit($employee, $validated);
        } catch (HttpException $e) {
            return back()->withErrors(['leave' => $e->getMessage()])->withInput();
        }

        return redirect()->route('app.hr.leaves.mine')->with('status', __('Leave request submitted.'));
    }

    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $employee = $this->currentEmployee($request);
        abort_unless($leave->employee_id === $employee->id, 403);

        $approval = $leave->approval;
        abort_if($approval === null, 422);
        try {
            app(ApprovalService::class)->cancel($approval, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        app(LeaveNotificationService::class)->notifyCancelled($leave);

        return redirect()->route('app.hr.leaves.mine')->with('status', __('Leave request cancelled.'));
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, __('You need an HR profile before submitting leave requests. Contact your Tenant Admin.'));

        return $employee;
    }
}
