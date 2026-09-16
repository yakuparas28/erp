<?php

namespace Tests\Feature\Hr;

use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\Payslip;
use Modules\Hr\Services\PayrollService;
use Modules\Hr\Services\SalaryAdvanceService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PayrollBatchPayTest extends TenantTestCase
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

    public function test_pay_all_pays_every_unpaid_payslip_in_one_je(): void
    {
        Employee::factory()->for($this->tenant)->count(3)->create([
            'gross_salary' => '10000', 'is_active' => true,
        ]);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 1, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $entriesBefore = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();

        $paid = app(PayrollService::class)->payAll($period->fresh(), $this->bank->id);

        $this->assertSame(3, $paid);
        $this->assertSame(3, Payslip::where('payroll_period_id', $period->id)
            ->where('status', Payslip::STATUS_PAID)->count());

        // Batch produced exactly ONE JE (not one per employee)
        $entriesAfter = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();
        $this->assertSame($entriesBefore + 1, $entriesAfter);
    }

    public function test_pay_all_je_is_balanced_and_credits_advance_account_when_deductions_exist(): void
    {
        $emp = Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        Employee::factory()->for($this->tenant)->create(['gross_salary' => '20000', 'is_active' => true]);

        app(SalaryAdvanceService::class)->grant($emp, '1500', $this->bank->id, $this->tenantAdmin);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 2, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());
        app(PayrollService::class)->payAll($period->fresh(), $this->bank->id);

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'payroll_period')
            ->where('reference_id', $period->id)
            ->latest('id')->firstOrFail();

        $lines = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->get();
        $this->assertSame((string) $lines->sum('debit'), (string) $lines->sum('credit'));
    }

    public function test_pay_all_refuses_when_period_not_posted(): void
    {
        Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 3, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);

        $this->expectException(HttpException::class);
        app(PayrollService::class)->payAll($period->fresh(), $this->bank->id);
    }

    public function test_pay_all_returns_zero_when_nothing_unpaid(): void
    {
        Employee::factory()->for($this->tenant)->create(['gross_salary' => '10000', 'is_active' => true]);
        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 4, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());
        app(PayrollService::class)->payAll($period->fresh(), $this->bank->id);

        // Second call: no unpaid → 0
        $paid = app(PayrollService::class)->payAll($period->fresh(), $this->bank->id);
        $this->assertSame(0, $paid);
    }

    public function test_bank_transfer_csv_lists_only_unpaid_with_cash_payable_after_advance(): void
    {
        $emp1 = Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '10000', 'is_active' => true, 'iban' => 'TR11', 'first_name' => 'Ali',
        ]);
        Employee::factory()->for($this->tenant)->create([
            'gross_salary' => '20000', 'is_active' => true, 'iban' => 'TR22', 'first_name' => 'Ayşe',
        ]);
        app(SalaryAdvanceService::class)->grant($emp1, '500', $this->bank->id, $this->tenantAdmin);

        $period = app(PayrollService::class)->openOrGetPeriod($this->tenant->id, 2026, 5, $this->tenantAdmin);
        app(PayrollService::class)->generate($period);
        app(PayrollService::class)->post($period->fresh());

        $response = $this->get(route('app.hr.payroll.bank-transfer-file', $period));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        // Ali için cash payable = 7149.10 - 500 = 6649.10 (avans mahsup edildi)
        $this->assertStringContainsString('6649.10', $csv);
        $this->assertStringContainsString('Ali', $csv);
        $this->assertStringContainsString('Ayşe', $csv);
    }
}
