<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function index(Request $request): View
    {
        // 'flow' route defaults'tan gelir:
        //   'receipt'  → Müşteri Tahsilatı ekranı (is_customer partner + sale invoice)
        //   'payment'  → Tedarikçi Ödemesi ekranı (is_supplier partner + purchase invoice)
        //   null       → Klasik "tüm ödemeler" admin listesi
        $flow = $request->route()?->defaults['flow'] ?? null;

        $partnersQuery = Partner::query();
        $paymentsQuery = Payment::with(['partner', 'journal', 'allocations'])->latest();

        if ($flow === 'receipt') {
            $partnersQuery->where('is_customer', true);
            $paymentsQuery->whereHas('partner', fn ($q) => $q->where('is_customer', true));
        } elseif ($flow === 'payment') {
            $partnersQuery->where('is_supplier', true);
            $paymentsQuery->whereHas('partner', fn ($q) => $q->where('is_supplier', true));
        }

        $payments = $paymentsQuery->get();

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
            'partners' => $partnersQuery->orderBy('name')->get(),
            'cashAndBankJournals' => Journal::whereIn('type', ['cash', 'bank'])->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
            'flow' => $flow,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'journal_id' => ['required', Rule::exists('journals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'currency_id' => [
                'nullable',
                Rule::exists('currencies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_functional', false)),
            ],
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

        $fxRevaluationsByInvoice = FxRevaluation::where('payment_id', $payment->id)
            ->get()
            ->groupBy('invoice_id')
            ->map(fn ($revaluations) => $revaluations->reduce(
                fn (string $carry, FxRevaluation $revaluation) => bcadd($carry, $revaluation->difference_amount, 4),
                '0.0000',
            ));

        return view('accounting::payments.show', [
            'payment' => $payment,
            'openInvoices' => $openInvoices,
            'fxRevaluationsByInvoice' => $fxRevaluationsByInvoice,
        ]);
    }

    public function storeAllocation(Request $request, Payment $payment): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
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
