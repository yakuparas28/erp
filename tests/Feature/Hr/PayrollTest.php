<?php

namespace Tests\Feature\Hr;

use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\PayrollPeriod;
use Modules\Hr\Models\Payslip;
use Modules\Hr\Services\PayrollService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PayrollTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_compute_matches_expected_breakdown_for_10000_gross(): void
    {
        $result = app(PayrollService::class)->compute('10000');

        // 10000 × %14 = 1400 SGK worker
        // 10000 × %1 = 100 unemployment worker
        // Base = 10000 − 1500 = 8500
        // Income tax = 8500 × %15 = 1275
        // Stamp = 10000 × %0.759 = 75.9
        // Total ded = 1400 + 100 + 1275 + 75.9 = 2850.9
        // Net = 10000 − 2850.9 = 7149.1
        $this->assertSame('1400.0000', $result['sgk_worker']);
        $this->assertSame('100.0000', $result['unemployment_worker']);
        $this->assertSame('1275.0000', $result['income_tax']);
        $this->assertSame('75.9000', $result['stamp_tax']);
        $this->assertSame('2850.9000', $result['total_deductions']);
        $this->assertSame('7149.1000', $result['net_salary']);

        // Employer cost = 10000 + %20.5 + %2 = 12250
        $this->assertSame('2050.0000', $result['sgk_employer']);
        $this->assertSame('200.0000', $result['unemployment_employer']);
        $this->assertSame('12250.0000', $result['total_employer_cost']);
    }

    public function test_generate_creates_payslips_only_for_active_employees_with_salary(): void
    {
        $withSalary = Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '15000', 'is_active' => true,
        ]);
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => null, 'is_active' => true,
        ]);
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '5000', 'is_active' => false,
        ]);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 9, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);

        $slips = Payslip::where('payroll_period_id', $period->id)->get();
        $this->assertCount(1, $slips);
        $this->assertSame($withSalary->id, $slips->first()->employee_id);
        $this->assertSame(PayrollPeriod::STATUS_CALCULATED, $period->fresh()->status);
    }

    public function test_post_creates_a_balanced_journal_entry(): void
    {
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '10000', 'is_active' => true, 'salary_expense_type' => 'admin',
        ]);
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '20000', 'is_active' => true, 'salary_expense_type' => 'direct_labor',
        ]);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 10, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $this->assertSame(PayrollPeriod::STATUS_POSTED, $period->fresh()->status);

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'payroll_period')
            ->where('reference_id', $period->id)
            ->firstOrFail();

        $lines = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->get();
        $debit = $lines->sum('debit');
        $credit = $lines->sum('credit');
        $this->assertSame((string) $debit, (string) $credit, 'Debits must equal credits');
    }

    public function test_pay_slip_transitions_to_paid_and_writes_je(): void
    {
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '10000', 'is_active' => true,
        ]);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 11, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $slip = Payslip::where('payroll_period_id', $period->id)->firstOrFail();
        $bank = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();

        $paid = app(PayrollService::class)->paySlip($slip, $bank->id);

        $this->assertSame(Payslip::STATUS_PAID, $paid->status);
        $this->assertSame($bank->id, $paid->paid_from_journal_id);
    }

    public function test_cannot_regenerate_a_posted_period(): void
    {
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '10000', 'is_active' => true,
        ]);
        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 12, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $this->expectException(HttpException::class);
        app(PayrollService::class)->generate($period->fresh());
    }
}
