<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\BankStatementImportService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BankStatementController extends Controller
{
    public function __construct(private readonly BankStatementImportService $importer) {}

    public function index(): View
    {
        return view('accounting::bank-statements.index', [
            'statements' => BankStatement::with(['bankJournal', 'uploader'])
                ->withCount('lines')
                ->latest('id')->paginate(30),
            'bankJournals' => Journal::where('type', 'bank')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'bank_journal_id' => [
                'required',
                Rule::exists('journals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('type', 'bank')),
            ],
            'file' => ['required', 'file', 'max:10240', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/octet-stream'],
        ]);

        $journal = Journal::findOrFail($validated['bank_journal_id']);

        try {
            $statement = $this->importer->import($journal, $request->file('file'), $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.bank-statements.show', $statement)
            ->with('status', __(':n rows imported, :m auto-matched.', ['n' => $statement->total_lines, 'm' => $statement->matched_lines]));
    }

    public function show(BankStatement $statement): View
    {
        $statement->load(['bankJournal', 'uploader']);
        $lines = BankStatementLine::where('bank_statement_id', $statement->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return view('accounting::bank-statements.show', [
            'statement' => $statement,
            'lines' => $lines,
            'openPayments' => Payment::where('journal_id', $statement->bank_journal_id)
                ->whereBetween('payment_date', [$statement->period_start, $statement->period_end])
                ->with('partner')->get(),
        ]);
    }

    public function matchLine(Request $request, BankStatementLine $line): RedirectResponse
    {
        $validated = $request->validate([
            'matched_type' => ['required', Rule::in(['payment', 'check_and_note', 'card_payment', 'journal_entry'])],
            'matched_id' => ['required', 'integer'],
        ]);

        try {
            $this->importer->matchManually($line, $validated['matched_type'], (int) $validated['matched_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return back()->with('status', __('Line matched.'));
    }

    public function ignoreLine(BankStatementLine $line): RedirectResponse
    {
        $this->importer->ignore($line);

        return back()->with('status', __('Line ignored.'));
    }

    public function unmatchLine(BankStatementLine $line): RedirectResponse
    {
        $this->importer->unmatch($line);

        return back()->with('status', __('Line unmatched.'));
    }
}
