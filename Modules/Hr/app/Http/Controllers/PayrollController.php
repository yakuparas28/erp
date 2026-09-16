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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    /**
     * Bir dönemdeki tüm ödenmemiş bordroları tek JE'de öde.
     */
    public function payAll(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'journal_id' => ['required', 'integer', Rule::exists('journals', 'id')
                ->where(fn ($q) => $q->where('tenant_id', $request->user()->tenant_id)),
            ],
        ]);

        try {
            $count = $this->payroll->payAll($period, (int) $validated['journal_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        return back()->with('status', __(':n payslips paid in one journal entry.', ['n' => $count]));
    }

    /**
     * Havale/EFT dosyası: ödenmemiş bordroların IBAN + tutar listesi.
     * Bankaya toplu havale girişi için CSV.
     */
    public function bankTransferFile(PayrollPeriod $period): StreamedResponse
    {
        abort_unless($period->status === PayrollPeriod::STATUS_POSTED, 422, __('Only posted periods can produce a transfer file.'));

        $period->load('payslips.employee');
        $unpaid = $period->payslips->where('status', Payslip::STATUS_CALCULATED);
        abort_if($unpaid->isEmpty(), 422, __('There are no unpaid payslips in this period.'));

        $filename = 'havale-'.$period->label().'.csv';

        return response()->streamDownload(function () use ($unpaid, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['first_name', 'last_name', 'iban', 'amount', 'description'], ';');
            foreach ($unpaid as $slip) {
                $emp = $slip->employee;
                fputcsv($out, [
                    $emp->first_name,
                    $emp->last_name,
                    $emp->iban ?? '',
                    number_format((float) $slip->cashPayable(), 2, '.', ''),
                    'Maas '.$period->label(),
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * "Bankadan gönderildi — tümünü ödendi olarak işaretle" akışı.
     * CSV export ettikten sonra kullanıcı ödemeyi bankadan yaptı; artık
     * sistem tarafında JE üretmek + payslip'leri işaretlemek istiyor.
     * Effect payAll() ile aynı — sadece adı farklı UX için.
     */
    public function markAllPaid(Request $request, PayrollPeriod $period): RedirectResponse
    {
        return $this->payAll($request, $period);
    }
}
