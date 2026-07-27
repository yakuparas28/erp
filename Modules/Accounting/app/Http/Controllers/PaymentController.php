<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Models\PaymentAllocation;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(): View
    {
        $payments = Payment::with(['partner', 'journal', 'allocations'])->latest()->get();

        // `Payment::unallocatedAmount()` sums `allocations()` via a fresh
        // query per call (it does not consult the eager-loaded collection),
        // so calling it once per row here would N+1. `allocations` is
        // already eager-loaded above, so we sum the in-memory collection
        // with bcmath instead of calling the model method.
        $payments->each(function (Payment $payment): void {
            $payment->setAttribute(
                'computed_unallocated_amount',
                bcsub($payment->amount, $this->sumAllocatedAmounts($payment->allocations), 4),
            );
        });

        return view('accounting::payments.index', [
            'payments' => $payments,
            'partners' => Partner::orderBy('name')->get(),
            'cashAndBankJournals' => Journal::whereIn('type', ['cash', 'bank'])->orderBy('name')->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'journal_id' => ['required', 'exists:journals,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
        ]);

        try {
            $payment = $this->payments->create(
                $request->user()->tenant_id,
                (int) $validated['partner_id'],
                (int) $validated['journal_id'],
                (string) $validated['amount'],
                (string) $validated['payment_date'],
                isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['journal_id' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.payments.show', $payment)->with('status', __('Payment recorded.'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['partner', 'journal', 'allocations.invoice']);

        $payment->setAttribute(
            'computed_unallocated_amount',
            bcsub($payment->amount, $this->sumAllocatedAmounts($payment->allocations), 4),
        );

        // Same N+1 hazard as index(): `Invoice::remainingBalance()` calls
        // `paidTotal()`, which sums `allocations()` via a fresh query per
        // call. Eager-load `lines.taxRate` (for total()) and `allocations`,
        // then compute the remaining balance from the loaded collections.
        $openInvoices = Invoice::with(['lines.taxRate', 'allocations'])
            ->where('partner_id', $payment->partner_id)
            ->where('currency_id', $payment->currency_id)
            ->whereIn('status', ['posted'])
            ->get()
            ->each(function (Invoice $invoice): void {
                $invoice->setAttribute(
                    'computed_remaining_balance',
                    bcsub($invoice->total(), $this->sumAllocatedAmounts($invoice->allocations), 4),
                );
            })
            ->filter(fn (Invoice $invoice) => bccomp($invoice->computed_remaining_balance, '0', 4) > 0)
            ->values();

        $fxRevaluationsByInvoice = FxRevaluation::where('payment_id', $payment->id)->get()->keyBy('invoice_id');

        return view('accounting::payments.show', [
            'payment' => $payment,
            'openInvoices' => $openInvoices,
            'fxRevaluationsByInvoice' => $fxRevaluationsByInvoice,
        ]);
    }

    public function storeAllocation(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        try {
            $this->payments->allocate($payment, $invoice, (string) $validated['amount']);
        } catch (HttpException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.payments.show', $payment)->with('status', __('Allocated.'));
    }

    /**
     * @param  Collection<int, PaymentAllocation>  $allocations
     */
    private function sumAllocatedAmounts(Collection $allocations): string
    {
        return $allocations->reduce(
            fn (string $carry, PaymentAllocation $allocation) => bcadd($carry, $allocation->allocated_amount, 4),
            '0.0000',
        );
    }
}
