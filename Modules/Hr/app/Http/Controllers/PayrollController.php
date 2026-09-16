<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\Journal;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\PayrollPeriod;
use Modules\Hr\Models\Payslip;
use Modules\Hr\Services\PayrollService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(): View
    {
        return view('hr::payroll.index', [
            'periods' => PayrollPeriod::withCount('payslips')->orderByDesc('year')->orderByDesc('month')->paginate(24),
            'employeesWithSalary' => Employee::where('is_active', true)->whereNotNull('gross_salary')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $period = $this->payroll->openOrGetPeriod(
            $request->user()->tenant_id,
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user(),
        );

        return redirect()->route('app.hr.payroll.show', $period);
    }

    public function show(PayrollPeriod $period): View
    {
        $period->load([
            'payslips.employee.department',
            'payslips.paidFromJournal',
        ]);

        return view('hr::payroll.show', [
            'period' => $period,
            'journals' => Journal::whereIn('type', ['cash', 'bank'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function generate(PayrollPeriod $period): RedirectResponse
    {
        try {
            $this->payroll->generate($period);
        } catch (HttpException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', __('Payslips generated.'));
    }

    public function post(PayrollPeriod $period): RedirectResponse
    {
        try {
            $this->payroll->post($period);
        } catch (HttpException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', __('Payroll posted to journal.'));
    }

    public function pay(Request $request, Payslip $slip): RedirectResponse
    {
        $validated = $request->validate([
            'journal_id' => ['required', 'integer', Rule::exists('journals', 'id')
                ->where(fn ($q) => $q->where('tenant_id', $request->user()->tenant_id)),
            ],
        ]);

        try {
            $this->payroll->paySlip($slip, (int) $validated['journal_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        return back()->with('status', __('Payslip paid.'));
    }
}
