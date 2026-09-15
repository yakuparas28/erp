<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Expenses\Http\Requests\StoreExpenseRequest;
use Modules\Expenses\Models\Expense;
use Modules\Expenses\Models\ExpenseCategory;
use Modules\Expenses\Services\ExpenseService;
use Modules\Hr\Models\Employee;

/**
 * Personel kendi masraflarını yönetir (My Expenses).
 * Onaylayıcı akışı ayrı bir controller'da (ApprovalController).
 */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(): View
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        $expenses = $employee
            ? Expense::where('employee_id', $employee->id)->with('category')->latest('expense_date')->latest('id')->get()
            : collect();

        return view('expenses::my.index', [
            'expenses' => $expenses,
            'employee' => $employee,
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        abort_if($employee === null, 422, __('You need an HR profile before submitting expenses. Contact your Tenant Admin.'));

        $data = $request->validated();
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $qty = (float) $data['qty'];
        $unit = $category->isFlatRate() ? (float) $category->unit_price : (float) ($data['unit_price'] ?? 0);
        $total = $this->service->computeTotal($category, $qty, $unit);

        $expense = Expense::create([
            'employee_id' => $employee->id,
            'expense_category_id' => $category->id,
            'description' => $data['description'],
            'expense_date' => $data['expense_date'],
            'qty' => $qty,
            'unit_price' => $unit,
            'total_amount' => $total,
            'currency_code' => $data['currency_code'] ?? 'TRY',
            'paid_by' => $data['paid_by'],
            'notes' => $data['notes'] ?? null,
            'reference' => $data['reference'] ?? null,
            'status' => Expense::STATUS_DRAFT,
        ]);

        if ($request->hasFile('receipt')) {
            $expense->update(['receipt_path' => $request->file('receipt')->store("expenses/{$expense->id}", 'public')]);
        }

        return redirect()->route('app.expenses.mine')->with('status', __('Expense recorded.'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        abort_unless($employee !== null && $expense->employee_id === $employee->id, 403);
        abort_unless($expense->isEditable(), 422, __('Only draft or refused expenses can be edited.'));

        $data = $request->validated();
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $qty = (float) $data['qty'];
        $unit = $category->isFlatRate() ? (float) $category->unit_price : (float) ($data['unit_price'] ?? 0);

        $expense->update([
            'expense_category_id' => $category->id,
            'description' => $data['description'],
            'expense_date' => $data['expense_date'],
            'qty' => $qty,
            'unit_price' => $unit,
            'total_amount' => $this->service->computeTotal($category, $qty, $unit),
            'currency_code' => $data['currency_code'] ?? 'TRY',
            'paid_by' => $data['paid_by'],
            'notes' => $data['notes'] ?? null,
            'reference' => $data['reference'] ?? null,
        ]);

        if ($request->hasFile('receipt')) {
            $expense->update(['receipt_path' => $request->file('receipt')->store("expenses/{$expense->id}", 'public')]);
        }

        return back()->with('status', __('Expense updated.'));
    }

    public function submit(Expense $expense): RedirectResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        abort_unless($employee !== null && $expense->employee_id === $employee->id, 403);

        $this->service->submit($expense);

        return back()->with('status', __('Expense submitted for approval.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        abort_unless($employee !== null && $expense->employee_id === $employee->id, 403);
        abort_unless($expense->status === Expense::STATUS_DRAFT, 422, __('Only draft expenses can be deleted.'));

        $expense->delete();

        return back()->with('status', __('Expense deleted.'));
    }
}
