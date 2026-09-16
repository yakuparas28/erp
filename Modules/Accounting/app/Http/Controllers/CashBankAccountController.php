<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountStatementService;

/**
 * Kasa & Banka hesapları — journals tablosundan `cash`/`bank` tipli
 * kayıtları CRUD eder. Aynı tabloyu payment akışı da kullanır; buradaki
 * kaydın chart_of_account_id'si JE'de dr/cr edilecek hesabı belirler.
 */
class CashBankAccountController extends Controller
{
    public function statement(Journal $journal, Request $request, AccountStatementService $statements): View
    {
        abort_unless($journal->isCashOrBank(), 404);
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->endOfMonth()->toDateString();

        return view('accounting::cash-bank-accounts.statement', [
            'journal' => $journal->load(['currency', 'chartOfAccount']),
            'statement' => $statements->build($journal, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function index(Request $request): View
    {
        return view('accounting::cash-bank-accounts.index', [
            'accounts' => Journal::whereIn('type', ['cash', 'bank'])
                ->with(['currency', 'chartOfAccount'])
                ->orderByDesc('is_active')->orderBy('type')->orderBy('name')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'cashAccounts' => ChartOfAccount::where('code', 'like', '100%')->orderBy('code')->get(),
            'bankAccounts' => ChartOfAccount::where('code', 'like', '102%')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $account = new Journal($validated);
        $account->tenant_id = $request->user()->tenant_id;
        $account->save();

        return back()->with('status', __(':name added.', ['name' => $account->name]));
    }

    public function update(Request $request, Journal $journal): RedirectResponse
    {
        abort_unless($journal->isCashOrBank(), 404);
        $validated = $this->validated($request, $journal);

        $journal->update($validated);

        return back()->with('status', __(':name updated.', ['name' => $journal->name]));
    }

    public function destroy(Journal $journal): RedirectResponse
    {
        abort_unless($journal->isCashOrBank(), 404);

        // Sistem üzerinde payment/entry varsa silme — soft-disable
        if ($journal->chart_of_account_id === null || Payment::where('journal_id', $journal->id)->exists()) {
            $journal->update(['is_active' => false]);

            return back()->with('status', __(':name archived (has activity, cannot be deleted).', ['name' => $journal->name]));
        }

        $journal->delete();

        return back()->with('status', __(':name deleted.', ['name' => $journal->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Journal $existing = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', Rule::in(['cash', 'bank'])],
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('journals', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($existing?->id),
            ],
            'bank_name' => ['nullable', 'string', 'max:128'],
            'iban' => ['nullable', 'string', 'max:34'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'currency_id' => [
                'nullable',
                Rule::exists('currencies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'chart_of_account_id' => [
                'nullable',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
