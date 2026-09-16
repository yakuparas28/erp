<?php

namespace Tests\Feature\Hr;

use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\Payslip;
use Modules\Hr\Models\SalaryAdvance;
use Modules\Hr\Services\PayrollService;
use Modules\Hr\Services\SalaryAdvanceService;
use Tests\TenantTestCase;

class SalaryAdvanceTest extends TenantTestCase
{
    private Journal $bank;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
        $this->bank = Journal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();
    }

    public function test_grant_creates_outstanding_advance_and_writes_je(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);

        $adv = app(SalaryAdvanceService::class)->grant($emp, '2000', $this->bank->id, $this->tenantAdmin);

        $this->assertSame(SalaryAdvance::STATUS_OUTSTANDING, $adv->status);
        $this->assertSame('2000.0000', (string) $adv->amount);
        $this->assertSame($this->bank->id, $adv->paid_from_journal_id);
    }

    public function test_outstanding_advance_is_deducted_from_generated_payslip(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        app(SalaryAdvanceService::class)->grant($emp, '2000', $this->bank->id, $this->tenantAdmin);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 9, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);

        $slip = Payslip::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertSame('2000.0000', (string) $slip->advance_deducted);
        // Net 7149.10, advance 2000, cash payable 5149.10
        $this->assertSame('5149.1000', $slip->cashPayable());
    }

    public function test_advance_is_capped_at_net_when_it_exceeds(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        // 10000 avans — net (~7149) altında olmalı, sadece net kadar mahsup
        app(SalaryAdvanceService::class)->grant($emp, '10000', $this->bank->id, $this->tenantAdmin);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 10, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);

        $slip = Payslip::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertSame('7149.1000', (string) $slip->advance_deducted);
        $this->assertSame('0.0000', $slip->cashPayable());
    }

    public function test_paying_slip_marks_advance_deducted(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        $adv = app(SalaryAdvanceService::class)->grant($emp, '2000', $this->bank->id, $this->tenantAdmin);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 11, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $slip = Payslip::where('payroll_period_id', $period->id)->firstOrFail();
        app(PayrollService::class)->paySlip($slip, $this->bank->id);

        $this->assertSame(SalaryAdvance::STATUS_DEDUCTED, $adv->fresh()->status);
        $this->assertSame($slip->id, $adv->fresh()->deducted_in_payslip_id);
    }

    public function test_second_period_ignores_already_deducted_advance(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        app(SalaryAdvanceService::class)->grant($emp, '2000', $this->bank->id, $this->tenantAdmin);

        // İlk ay: mahsup
        $p1 = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 1, $this->tenantAdmin);
        app(PayrollService::class)->generate($p1);
        app(PayrollService::class)->post($p1->fresh());
        $slip1 = Payslip::where('payroll_period_id', $p1->id)->firstOrFail();
        app(PayrollService::class)->paySlip($slip1, $this->bank->id);

        // İkinci ay: aynı avans tekrar düşülmemeli
        $p2 = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 2, $this->tenantAdmin);
        app(PayrollService::class)->generate($p2);
        $slip2 = Payslip::where('payroll_period_id', $p2->id)->firstOrFail();

        $this->assertSame('0.0000', (string) $slip2->advance_deducted);
    }
}
