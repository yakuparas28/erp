<?php

namespace Modules\Hr\Services;

use App\Models\Approval\Approval;
use App\Services\Approval\ApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hr\Models\CriticalDate;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;

/**
 * İzin talebi yaşam döngüsü: gün sayısı hesabı → kritik tarih kontrolü →
 * approval submit. Onay tamamlandığında bakiye düşer (deductBalance) —
 * bakiye düşürme onay sonrası çağrılır çünkü ApprovalService subject-
 * agnostik; controller katmanı hook eder.
 */
class LeaveRequestService
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $leaveType = LeaveType::where('tenant_id', $employee->tenant_id)
            ->where('id', $data['leave_type_id'])
            ->firstOrFail();

        $totalDays = $this->calculateDays(
            $data['start_date'],
            $data['end_date'],
            $data['half_day_type'] ?? null,
            $leaveType->unit,
        );

        $this->assertBalanceAvailable($employee, $leaveType, $totalDays);
        $this->assertNotBlockedByCriticalDate($employee, $data['start_date'], $data['end_date']);

        return DB::transaction(function () use ($employee, $data, $leaveType, $totalDays): LeaveRequest {
            $request = LeaveRequest::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'half_day_type' => $data['half_day_type'] ?? null,
                'total_days' => $totalDays,
                'return_to_work_date' => $data['return_to_work_date'] ?? null,
                'reason' => $data['reason'] ?? null,
                'travel_allowance_requested' => $data['travel_allowance_requested'] ?? false,
                'ticket_no' => $data['ticket_no'] ?? null,
                'document_path' => $data['document_path'] ?? null,
            ]);

            $this->approvals->submit($request, $employee->user);

            return $request->fresh(['leaveType', 'employee']);
        });
    }

    /**
     * ApprovalService::approve tamamlanınca çağrılır. Approval status
     * 'approved' ise ve tür bakiyeden düşüyorsa cari bakiyeyi güncelller.
     */
    public function afterDecision(LeaveRequest $request): void
    {
        $request->refresh();
        $approval = $request->approval;

        if ($approval === null || $approval->status !== 'approved') {
            return;
        }

        if (! $request->leaveType?->deducts_from_balance) {
            return;
        }

        $employee = $request->employee;
        $employee->decrement('annual_leave_balance', (float) $request->total_days);
    }

    public function calculateDays(string $startDate, string $endDate, ?string $halfDayType, string $unit): string
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end) + 1;

        if ($halfDayType !== null || $unit === 'half_day') {
            return '0.50';
        }

        return number_format($days, 2, '.', '');
    }

    private function assertBalanceAvailable(Employee $employee, LeaveType $type, string $totalDays): void
    {
        if (! $type->deducts_from_balance) {
            return;
        }

        abort_if(
            bccomp((string) $employee->annual_leave_balance, $totalDays, 2) < 0,
            422,
            __('Insufficient leave balance: :remaining left, :requested requested.', [
                'remaining' => $employee->annual_leave_balance,
                'requested' => $totalDays,
            ]),
        );
    }

    private function assertNotBlockedByCriticalDate(Employee $employee, string $start, string $end): void
    {
        $blocking = CriticalDate::where('tenant_id', $employee->tenant_id)
            ->where('block_leave_requests', true)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->first();

        abort_if(
            $blocking !== null,
            422,
            __('Leave requests are blocked during the critical period ":name".', ['name' => $blocking?->name ?? '']),
        );
    }
}
