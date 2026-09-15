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
        $this->service->approve($expense, auth()->user());

        return back()->with('status', __('Expense approved.'));
    }

    public function refuse(RefuseExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->service->refuse($expense, auth()->user(), (string) $request->validated()['refuse_reason']);

        return back()->with('status', __('Expense refused.'));
    }

    public function post(Expense $expense): RedirectResponse
    {
        // Belt-and-suspenders: route zaten `permission:post expense` altında
        // ama controller'da da explicit tuttum ki routes yeniden düzenlenirse
        // yetki kaybolmasın.
        abort_unless(auth()->user()?->can('post expense'), 403);

        $this->service->postToAccounting($expense);

        return back()->with('status', __('Expense posted to accounting journal.'));
    }
}
