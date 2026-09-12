<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveBalance;

class LeaveBalanceController extends Controller
{
    public function index(): View
    {
        $year = (int) request()->input('year', date('Y'));

        return view('hr::leaves.balances', [
            'year' => $year,
            'employees' => Employee::with(['user'])
                ->orderBy('first_name')
                ->get(),
            'balances' => LeaveBalance::where('year', $year)
                ->get()
                ->keyBy('employee_id'),
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'current_year_entitlement' => ['required', 'numeric', 'min:0'],
            'carried_from_previous' => ['nullable', 'numeric', 'min:0'],
            'manual_adjustment' => ['nullable', 'numeric'],
            'adjustment_reason' => ['nullable', 'string', 'max:500'],
        ]);

        LeaveBalance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $validated['employee_id'],
                'year' => $validated['year'],
            ],
            [
                'current_year_entitlement' => $validated['current_year_entitlement'],
                'carried_from_previous' => $validated['carried_from_previous'] ?? 0,
                'manual_adjustment' => $validated['manual_adjustment'] ?? 0,
                'adjustment_reason' => $validated['adjustment_reason'] ?? null,
                'adjusted_by' => $request->user()->id,
            ],
        );

        Employee::where('id', $validated['employee_id'])->update([
            'annual_leave_balance' => bcadd(
                bcadd((string) ($validated['carried_from_previous'] ?? '0'), (string) $validated['current_year_entitlement'], 2),
                (string) ($validated['manual_adjustment'] ?? '0'),
                2,
            ),
        ]);

        return redirect()->route('app.hr.leave-balances.index', ['year' => $validated['year']])->with('status', __('Balance updated.'));
    }
}
