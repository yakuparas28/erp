<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Expenses\Http\Requests\RefuseExpenseRequest;
use Modules\Expenses\Models\Expense;
use Modules\Expenses\Services\ExpenseService;

class ExpenseApprovalController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(): View
    {
        return view('expenses::approvals.index', [
            'pending' => Expense::where('status', Expense::STATUS_SUBMITTED)
                ->with(['employee', 'category'])->orderBy('submitted_at')->get(),
            'approved' => Expense::where('status', Expense::STATUS_APPROVED)
                ->with(['employee', 'category'])->orderByDesc('approved_at')->limit(50)->get(),
        ]);
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $this->service->approve($expense, auth()->user());

        return back()->with('status', __('Expense approved.'));
    }

    public function refuse(RefuseExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('refuse', $expense);
        $this->service->refuse($expense, auth()->user(), (string) $request->validated()['refuse_reason']);

        return back()->with('status', __('Expense refused.'));
    }

    public function post(Expense $expense): RedirectResponse
    {
        $this->authorize('post', $expense);
        $this->service->postToAccounting($expense);

        return back()->with('status', __('Expense posted to accounting journal.'));
    }
}
