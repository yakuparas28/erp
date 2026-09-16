<?php

namespace Modules\Hr\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\PayrollPeriod;
use Modules\Hr\Models\Payslip;

/**
 * Basit bordro (Seviye 1). Sabit oranlar, ay bazlı toplu tahakkuk.
 *
 * Oranlar (TR 2026 basit form):
 *   İşçi: SGK %14 + İşsizlik %1 = %15
 *   Gelir Vergisi: (brüt − işçi kesintileri) × %15 (tek dilim)
 *   Damga: brüt × %0.759
 *   İşveren: SGK %20.5 + İşsizlik %2 = %22.5
 *
 * Kısıt: Kümülatif matrah dilim geçişi, asgari ücret istisnası,
 * ek ödeme/kesinti, prim/ikramiye — HEPSİ Seviye 2'de.
 *
 * JE (post edildiğinde tek satırda):
 *   dr 720 / 770  (toplam ücret gideri + işveren SGK/işsizlik payı)
 *   cr 335        (personele net borç)
 *   cr 360        (Gelir + Damga vergisi toplamı)
 *   cr 361        (SGK işçi + işsizlik + SGK işveren + işsizlik işveren)
 *
 * Ödeme (payslip bazlı):
 *   dr 335        (net)
 *   cr 102/100    (banka/kasa)
 */
class PayrollService
{
    public const RATE_SGK_WORKER = '14';

    public const RATE_UNEMP_WORKER = '1';

    public const RATE_INCOME_TAX = '15';

    public const RATE_STAMP = '0.759';

    public const RATE_SGK_EMPLOYER = '20.5';

    public const RATE_UNEMP_EMPLOYER = '2';

    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly AccountingDefaultsService $defaults,
        private readonly SalaryAdvanceService $advances,
    ) {}

    public function openOrGetPeriod(int $tenantId, int $year, int $month, User $creator): PayrollPeriod
    {
        $existing = PayrollPeriod::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)->where('month', $month)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $period = new PayrollPeriod([
            'year' => $year,
            'month' => $month,
            'status' => PayrollPeriod::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);
        $period->tenant_id = $tenantId;
        $period->save();

        return $period;
    }

    /**
     * Payslip'leri üretir (mevcut olanları siler + yeniden hesaplar).
     * Sadece is_active=true ve gross_salary>0 personel dahil edilir.
     */
    public function generate(PayrollPeriod $period): PayrollPeriod
    {
        abort_if($period->status === PayrollPeriod::STATUS_POSTED, 422, __('Cannot regenerate a posted period.'));

        return DB::transaction(function () use ($period) {
            Payslip::where('payroll_period_id', $period->id)->delete();

            $employees = Employee::withoutGlobalScopes()
                ->where('tenant_id', $period->tenant_id)
                ->where('is_active', true)
                ->whereNotNull('gross_salary')
                ->where('gross_salary', '>', 0)
                ->get();

            $totalGross = '0.0000';
            $totalDed = '0.0000';
            $totalNet = '0.0000';
            $totalEmployer = '0.0000';

            foreach ($employees as $emp) {
                $breakdown = $this->compute((string) $emp->gross_salary);

                // Avans mahsubu — outstanding toplam net'i geçemez.
                $outstanding = $this->advances->outstandingTotal($emp->id);
                $advanceCap = bccomp($outstanding, $breakdown['net_salary'], 4) > 0
                    ? $breakdown['net_salary']
                    : $outstanding;

                $slip = new Payslip(array_merge([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $emp->id,
                    'status' => Payslip::STATUS_CALCULATED,
                    'advance_deducted' => $advanceCap,
                ], $breakdown));
                $slip->tenant_id = $period->tenant_id;
                $slip->save();

                $totalGross = bcadd($totalGross, $breakdown['gross_salary'], 4);
                $totalDed = bcadd($totalDed, $breakdown['total_deductions'], 4);
                $totalNet = bcadd($totalNet, $breakdown['net_salary'], 4);
                $totalEmployer = bcadd($totalEmployer, $breakdown['total_employer_cost'], 4);
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_CALCULATED,
                'total_gross' => $totalGross,
                'total_deductions' => $totalDed,
                'total_net' => $totalNet,
                'total_employer_cost' => $totalEmployer,
            ]);

            return $period->fresh();
        });
    }

    /**
     * Bir brüt için tüm kesintileri hesapla.
     *
     * @return array{gross_salary:string, sgk_worker:string, unemployment_worker:string, income_tax:string, stamp_tax:string, total_deductions:string, net_salary:string, sgk_employer:string, unemployment_employer:string, total_employer_cost:string}
     */
    public function compute(string $gross): array
    {
        $sgkWorker = $this->pct($gross, self::RATE_SGK_WORKER);
        $unempWorker = $this->pct($gross, self::RATE_UNEMP_WORKER);
        $incomeBase = bcsub($gross, bcadd($sgkWorker, $unempWorker, 4), 4);
        $incomeTax = $this->pct($incomeBase, self::RATE_INCOME_TAX);
        $stamp = $this->pct($gross, self::RATE_STAMP);

        $ded = bcadd(bcadd(bcadd($sgkWorker, $unempWorker, 4), $incomeTax, 4), $stamp, 4);
        $net = bcsub($gross, $ded, 4);

        $sgkEmp = $this->pct($gross, self::RATE_SGK_EMPLOYER);
        $unempEmp = $this->pct($gross, self::RATE_UNEMP_EMPLOYER);
        $employerCost = bcadd(bcadd($gross, $sgkEmp, 4), $unempEmp, 4);

        return [
            'gross_salary' => $gross,
            'sgk_worker' => $sgkWorker,
            'unemployment_worker' => $unempWorker,
            'income_tax' => $incomeTax,
            'stamp_tax' => $stamp,
            'total_deductions' => $ded,
            'net_salary' => $net,
            'sgk_employer' => $sgkEmp,
            'unemployment_employer' => $unempEmp,
            'total_employer_cost' => $employerCost,
        ];
    }

    public function post(PayrollPeriod $period): PayrollPeriod
    {
        abort_unless($period->status === PayrollPeriod::STATUS_CALCULATED, 422, __('Only calculated periods can be posted.'));
        $period->load('payslips.employee');
        abort_if($period->payslips->isEmpty(), 422, __('There are no payslips in this period.'));

        return DB::transaction(function () use ($period) {
            $tenantId = $period->tenant_id;
            $acc720 = $this->defaults->accountByCode($tenantId, '720');
            $acc770 = $this->defaults->accountByCode($tenantId, '770');
            $acc335 = $this->defaults->accountByCode($tenantId, '335');
            $acc360 = $this->defaults->accountByCode($tenantId, '360');
            $acc361 = $this->defaults->accountByCode($tenantId, '361');

            // Toplamlar: 720 (direct_labor) / 770 (admin) ayrı bloklarda
            $labor720 = '0.0000';
            $labor770 = '0.0000';
            foreach ($period->payslips as $slip) {
                $emp = $slip->employee;
                $expType = $emp->salary_expense_type ?? 'admin';
                if ($expType === 'direct_labor') {
                    $labor720 = bcadd($labor720, (string) $slip->total_employer_cost, 4);
                } else {
                    $labor770 = bcadd($labor770, (string) $slip->total_employer_cost, 4);
                }
            }

            $totalNet = (string) $period->total_net;
            $totalTaxes = bcadd(
                (string) $period->payslips->sum('income_tax'),
                (string) $period->payslips->sum('stamp_tax'),
                4,
            );
            $totalSgk = bcadd(bcadd(
                (string) $period->payslips->sum('sgk_worker'),
                (string) $period->payslips->sum('unemployment_worker'),
                4,
            ), bcadd(
                (string) $period->payslips->sum('sgk_employer'),
                (string) $period->payslips->sum('unemployment_employer'),
                4,
            ), 4);

            $lines = [];
            if (bccomp($labor720, '0', 4) > 0) {
                $lines[] = ['account_id' => $acc720->id, 'debit' => $labor720, 'credit' => '0.0000'];
            }
            if (bccomp($labor770, '0', 4) > 0) {
                $lines[] = ['account_id' => $acc770->id, 'debit' => $labor770, 'credit' => '0.0000'];
            }
            $lines[] = ['account_id' => $acc335->id, 'debit' => '0.0000', 'credit' => $totalNet];
            $lines[] = ['account_id' => $acc360->id, 'debit' => '0.0000', 'credit' => $totalTaxes];
            $lines[] = ['account_id' => $acc361->id, 'debit' => '0.0000', 'credit' => $totalSgk];

            $this->journalEntries->write(
                tenantId: $tenantId,
                journalType: 'general',
                entryDate: now()->toDateString(),
                reference: $period,
                lines: $lines,
            );

            $period->update([
                'status' => PayrollPeriod::STATUS_POSTED,
                'posted_at' => now()->toDateString(),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Tek personele net ödeme. Avans mahsubu varsa:
     *   dr 335 (net toplam)
     *   cr 196 (mahsup edilen avans)
     *   cr banka/kasa (kalan nakit)
     * Avans yoksa cr 196 satırı yazılmaz — mevcut davranış korunur.
     */
    public function paySlip(Payslip $slip, int $journalId): Payslip
    {
        abort_unless($slip->status === Payslip::STATUS_CALCULATED, 422, __('This payslip is already paid.'));

        $journal = Journal::withoutGlobalScopes()->findOrFail($journalId);
        abort_unless(
            in_array($journal->type, ['cash', 'bank'], true) && $journal->tenant_id === $slip->tenant_id,
            422,
            __('Invalid journal.'),
        );

        return DB::transaction(function () use ($slip, $journal) {
            $acc335 = $this->defaults->accountByCode($slip->tenant_id, '335');
            $bankAccount = $journal->chart_of_account_id !== null
                ? ChartOfAccount::withoutGlobalScopes()->findOrFail($journal->chart_of_account_id)
                : $this->defaults->accountByCode($slip->tenant_id, $journal->type === 'cash' ? '100' : '102');

            $advanceDeducted = (string) $slip->advance_deducted;
            $cashPayable = bcsub((string) $slip->net_salary, $advanceDeducted, 4);
            $hasAdvance = bccomp($advanceDeducted, '0', 4) > 0;

            $lines = [
                ['account_id' => $acc335->id, 'debit' => $slip->net_salary, 'credit' => '0.0000'],
            ];
            if ($hasAdvance) {
                $acc196 = $this->defaults->accountByCode($slip->tenant_id, '196');
                $lines[] = ['account_id' => $acc196->id, 'debit' => '0.0000', 'credit' => $advanceDeducted];
            }
            if (bccomp($cashPayable, '0', 4) > 0) {
                $lines[] = ['account_id' => $bankAccount->id, 'debit' => '0.0000', 'credit' => $cashPayable];
            }

            $this->journalEntries->write(
                tenantId: $slip->tenant_id,
                journalType: $journal->type,
                entryDate: now()->toDateString(),
                reference: $slip,
                lines: $lines,
            );

            $slip->update([
                'status' => Payslip::STATUS_PAID,
                'paid_at' => now()->toDateString(),
                'paid_from_journal_id' => $journal->id,
            ]);

            // Mahsup edilen avansları FIFO ile deducted işaretle
            if ($hasAdvance) {
                $remaining = $advanceDeducted;
                $idsToMark = [];
                foreach ($this->advances->outstandingList($slip->employee_id) as $adv) {
                    if (bccomp($remaining, '0', 4) <= 0) {
                        break;
                    }
                    // Kısmi mahsup destekli değil (avans > net cap edilmişti,
                    // sadece tam mahsup edilenler işaretlenir). Kısmi mahsup
                    // gerekirse ileride avans split edilebilir.
                    if (bccomp((string) $adv->amount, $remaining, 4) <= 0) {
                        $idsToMark[] = $adv->id;
                        $remaining = bcsub($remaining, (string) $adv->amount, 4);
                    }
                }
                $this->advances->markDeducted($idsToMark, $slip->id);
            }

            return $slip->fresh();
        });
    }

    private function pct(string $base, string $rate): string
    {
        return bcdiv(bcmul($base, $rate, 6), '100', 4);
    }
}
