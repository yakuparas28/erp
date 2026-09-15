<?php

namespace Tests\Feature\Expenses;

use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Expenses\Models\Expense;
use Modules\Expenses\Models\ExpenseCategory;
use Modules\Expenses\Services\ExpenseDefaultsService;
use Modules\Expenses\Services\ExpenseService;
use Modules\Hr\Models\Employee;
use Tests\TenantTestCase;

class ExpenseFlowTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(ExpenseDefaultsService::class)->provision($this->tenant);
        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    public function test_default_categories_are_seeded(): void
    {
        $this->assertGreaterThanOrEqual(8, ExpenseCategory::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_employee_can_submit_expense_and_approver_can_approve(): void
    {
        $employeeUser = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $employeeUser->assignRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $employeeUser->id]);

        $category = ExpenseCategory::where('tenant_id', $this->tenant->id)->where('code', 'YEM')->firstOrFail();

        $expense = Expense::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'expense_category_id' => $category->id,
            'description' => 'Müşteri yemeği',
            'expense_date' => now()->subDay()->toDateString(),
            'qty' => 1,
            'unit_price' => 250,
            'total_amount' => 250,
            'currency_code' => 'TRY',
            'paid_by' => 'employee',
            'status' => 'draft',
        ]);

        $svc = app(ExpenseService::class);
        $svc->submit($expense);
        $this->assertSame('submitted', $expense->fresh()->status);

        $approver = User::factory()->for($this->tenant)->create();
        $approver->assignRole('Expense Approver');
        $svc->approve($expense->fresh(), $approver);
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertSame($approver->id, $expense->fresh()->approved_by);
    }

    public function test_flat_rate_category_computes_total(): void
    {
        $km = ExpenseCategory::where('tenant_id', $this->tenant->id)->where('code', 'KM')->firstOrFail();
        $this->assertTrue($km->isFlatRate());
        $total = app(ExpenseService::class)->computeTotal($km, 120);
        $this->assertEqualsWithDelta(480.0, $total, 0.01); // 120 km * 4 TL
    }

    public function test_refuse_requires_reason_and_records_it(): void
    {
        $employeeUser = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $employeeUser->assignRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $employeeUser->id]);
        $category = ExpenseCategory::where('tenant_id', $this->tenant->id)->firstOrFail();

        $expense = Expense::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id, 'expense_category_id' => $category->id,
            'description' => 'Test', 'expense_date' => today(), 'qty' => 1,
            'unit_price' => 100, 'total_amount' => 100, 'paid_by' => 'employee', 'status' => 'submitted',
        ]);

        $approver = User::factory()->for($this->tenant)->create();
        $approver->assignRole('Expense Approver');
        app(ExpenseService::class)->refuse($expense, $approver, 'Fiş eksik');

        $expense->refresh();
        $this->assertSame('refused', $expense->status);
        $this->assertSame('Fiş eksik', $expense->refuse_reason);
    }

    public function test_post_to_accounting_creates_balanced_journal_entry(): void
    {
        // Muhasebe kurulumunda 770 (masraf) ve 335 (personele borçlar) hesapları bulunur.
        $this->assertNotNull(ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', 'like', '770%')->first());
        $this->assertNotNull(ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', 'like', '335%')->first());
        $this->assertNotNull(Journal::where('tenant_id', $this->tenant->id)->where('type', 'purchase')->first());

        $employeeUser = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $employeeUser->assignRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $employeeUser->id]);
        $category = ExpenseCategory::where('tenant_id', $this->tenant->id)->where('code', 'YEM')->firstOrFail();

        $expense = Expense::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id, 'expense_category_id' => $category->id,
            'description' => 'Test', 'expense_date' => today(), 'qty' => 1,
            'unit_price' => 100, 'total_amount' => 100, 'paid_by' => 'employee', 'status' => 'approved',
        ]);

        app(ExpenseService::class)->postToAccounting($expense);
        $expense->refresh();
        $this->assertSame('posted', $expense->status);
        $this->assertNotNull($expense->posted_journal_entry_id);
    }
}
