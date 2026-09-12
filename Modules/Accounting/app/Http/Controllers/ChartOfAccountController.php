<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        $accounts = ChartOfAccount::with('children')->orderBy('code')->get();

        return view('accounting::accounts.index', [
            'accounts' => $accounts,
            'rootAccounts' => $accounts->whereNull('parent_id')->values(),
            'systemAccounts' => $accounts->where('is_system', true)->sortBy('code')->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'parent_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $parent = ChartOfAccount::findOrFail($validated['parent_id']);

        if (! str_starts_with($validated['code'], $parent->code.'.')) {
            return back()->withErrors(['code' => __('Sub-account code must start with the parent code followed by a dot (e.g. :parent.01).', ['parent' => $parent->code])]);
        }

        ChartOfAccount::create([
            'parent_id' => $parent->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $parent->type,
            'is_system' => false,
        ]);

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Sub-account added.'));
    }

    public function update(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $account->update($validated);

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account updated.'));
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        if ($account->is_system) {
            return back()->withErrors(['account' => __('System accounts (Tekdüzen Hesap Planı) cannot be deleted.')]);
        }

        if ($account->children()->exists()) {
            return back()->withErrors(['account' => __('Delete the sub-accounts first.')]);
        }

        $isUsed = JournalEntryLine::where('account_id', $account->id)->exists();

        if ($isUsed) {
            return back()->withErrors(['account' => __('This account has journal entries and cannot be deleted.')]);
        }

        $account->delete();

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account deleted.'));
    }
}
