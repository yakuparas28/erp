<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\CheckAndNoteService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Çek/Senet portföyü. Alınan (`incoming`) ve verilen (`outgoing`) aynı
 * controller'ı flow parametresiyle paylaşır — route defaults'tan gelir.
 */
class CheckAndNoteController extends Controller
{
    public function __construct(private readonly CheckAndNoteService $service) {}

    public function index(Request $request): View
    {
        $direction = $request->route()?->defaults['direction'] ?? CheckAndNote::DIR_INCOMING;
        $status = $request->query('status');

        $query = CheckAndNote::with(['partner', 'endorsedToPartner', 'collectionBankJournal', 'currency'])
            ->where('direction', $direction)
            ->orderBy('maturity_date');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $items = $query->get();

        return view('accounting::checks-and-notes.index', [
            'items' => $items,
            'direction' => $direction,
            'status' => $status,
            'partners' => Partner::where($direction === CheckAndNote::DIR_INCOMING ? 'is_customer' : 'is_supplier', true)->orderBy('name')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'bankJournals' => Journal::where('type', 'bank')->where('is_active', true)->orderBy('name')->get(),
            'cashJournals' => Journal::where('type', 'cash')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Partner::where('is_supplier', true)->orderBy('name')->get(),
            'statusCounts' => CheckAndNote::where('direction', $direction)
                ->selectRaw('status, COUNT(*) as c, SUM(amount) as total')
                ->groupBy('status')->get()->keyBy('status'),
        ]);
    }

    public function show(CheckAndNote $note): View
    {
        return view('accounting::checks-and-notes.show', [
            'note' => $note->load(['partner', 'endorsedToPartner', 'collectionBankJournal', 'currency', 'creator']),
            'bankJournals' => Journal::where('type', 'bank')->where('is_active', true)->orderBy('name')->get(),
            'cashJournals' => Journal::where('type', 'cash')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Partner::where('is_supplier', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'instrument_no' => ['required', 'string', 'max:64'],
            'instrument_type' => ['required', Rule::in(['check', 'promissory_note'])],
            'direction' => ['required', Rule::in(['incoming', 'outgoing'])],
            'partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'drawer_name' => ['nullable', 'string', 'max:128'],
            'drawee_bank_name' => ['nullable', 'string', 'max:128'],
            'drawee_branch' => ['nullable', 'string', 'max:128'],
            'issue_date' => ['required', 'date'],
            'maturity_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => [
                'nullable',
                Rule::exists('currencies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $note = $this->service->register($validated, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['note' => $e->getMessage()])->withInput();
        }

        return redirect()->route('app.accounting.checks-and-notes.show', $note)->with('status', __('Instrument recorded.'));
    }

    public function endorse(Request $request, CheckAndNote $note): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'endorsed_to_partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        return $this->handle(fn () => $this->service->endorse($note, (int) $validated['endorsed_to_partner_id']), __('Endorsed.'), $note);
    }

    public function sendToBank(Request $request, CheckAndNote $note): RedirectResponse
    {
        $validated = $request->validate([
            'bank_journal_id' => ['required', 'integer'],
        ]);

        return $this->handle(fn () => $this->service->sendToBank($note, (int) $validated['bank_journal_id']), __('Sent to bank.'), $note);
    }

    public function markCollected(Request $request, CheckAndNote $note): RedirectResponse
    {
        $validated = $request->validate([
            'journal_id' => ['required', 'integer'],
        ]);

        return $this->handle(fn () => $this->service->markCollected($note, (int) $validated['journal_id']), __('Collected.'), $note);
    }

    public function markBounced(CheckAndNote $note): RedirectResponse
    {
        return $this->handle(fn () => $this->service->markBounced($note), __('Marked bounced.'), $note);
    }

    public function markPaid(Request $request, CheckAndNote $note): RedirectResponse
    {
        $validated = $request->validate([
            'journal_id' => ['required', 'integer'],
        ]);

        return $this->handle(fn () => $this->service->markPaid($note, (int) $validated['journal_id']), __('Marked paid.'), $note);
    }

    private function handle(\Closure $callback, string $message, CheckAndNote $note): RedirectResponse
    {
        try {
            $callback();
        } catch (HttpException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.checks-and-notes.show', $note)->with('status', $message);
    }
}
