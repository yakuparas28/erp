<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        return view('accounting::accounts.index', [
            'accounts' => ChartOfAccount::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
        ]);

        ChartOfAccount::create($validated);

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account added.'));
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        $isUsed = JournalEntryLine::where('account_id', $account->id)->exists();

        if ($isUsed) {
            return back()->withErrors(['account' => __('This account has journal entries and cannot be deleted.')]);
        }

        $account->delete();

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account deleted.'));
    }
}
