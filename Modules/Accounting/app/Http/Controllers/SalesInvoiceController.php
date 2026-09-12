<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\EInvoiceService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Sales\Models\SalesOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly EInvoiceService $eInvoices,
    ) {}

    public function index(): View
    {
        return view('accounting::sales-invoices.index', [
            'invoices' => Invoice::with(['partner', 'currency', 'lines.taxRate'])->where('type', 'sale')->latest()->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
            'salesOrders' => SalesOrder::whereIn('status', ['confirmed', 'done'])->with('partner')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'sales_order_id' => ['required', Rule::exists('sales_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'currency_id' => [
                'nullable',
                Rule::exists('currencies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_functional', false)),
            ],
        ]);

        $so = SalesOrder::findOrFail($validated['sales_order_id']);

        try {
            $invoice = $this->invoices->create(
                $request->user()->tenant_id,
                $so->partner_id,
                'sale',
                $so,
                isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['currency_id' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('Draft invoice created.'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['partner', 'currency', 'lines.product', 'lines.taxRate', 'source.lines.product']);
        $invoice->setAttribute('computed_total_tl', bcmul($invoice->total(), $invoice->exchangeRateOrOne(), 4));

        return view('accounting::sales-invoices.show', [
            'invoice' => $invoice,
            'taxRates' => TaxRate::where('type', 'sale')->orderBy('percentage')->get(),
        ]);
    }

    public function storeLine(Request $request, Invoice $invoice): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        try {
            $this->invoices->addLine(
                $invoice,
                (int) $validated['product_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
                isset($validated['tax_rate_id']) ? (int) $validated['tax_rate_id'] : null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('Line added.'));
    }

    public function post(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->post($invoice, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('Invoice posted.'));
    }

    public function sendEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->send($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('e-Invoice sent.'));
    }

    public function acceptEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->markAccepted($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('e-Invoice marked as accepted.'));
    }

    public function rejectEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->markRejected($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.sales-invoices.show', $invoice)->with('status', __('e-Invoice marked as rejected.'));
    }
}
