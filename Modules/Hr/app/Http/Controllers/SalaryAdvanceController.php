<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\Journal;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\SalaryAdvance;
use Modules\Hr\Services\SalaryAdvanceService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalaryAdvanceController extends Controller
{
    public function __construct(private readonly SalaryAdvanceService $advances) {}

    public function index(): View
    {
        return view('hr::salary-advances.index', [
            'advances' => SalaryAdvance::with(['employee.department', 'paidFromJournal', 'deductedInPayslip.period'])
                ->orderByDesc('granted_at')->orderByDesc('id')->paginate(50),
            'employees' => Employee::where('is_active', true)->whereNotNull('gross_salary')->orderBy('first_name')->get(),
            'journals' => Journal::whereIn('type', ['cash', 'bank'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'journal_id' => ['required', Rule::exists('journals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $this->advances->grant(
                $employee,
                (string) $validated['amount'],
                (int) $validated['journal_id'],
                $request->user(),
                $validated['notes'] ?? null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['advance' => $e->getMessage()]);
        }

        return back()->with('status', __('Advance granted.'));
    }
}
