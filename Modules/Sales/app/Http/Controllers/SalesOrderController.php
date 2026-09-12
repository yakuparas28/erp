<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Services\VariantGeneratorService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrders,
        private readonly VariantGeneratorService $variantGenerator,
    ) {}

    public function index(): View
    {
        return view('sales::orders.index', [
            'orders' => SalesOrder::with(['partner', 'location', 'lines', 'creator'])
                ->whereIn('status', ['confirmed', 'done', 'cancelled'])
                ->latest()->get(),
            'customers' => Partner::where('is_customer', true)->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'route_id' => [
                'nullable',
                Rule::exists('routes', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $so = $this->salesOrders->create(
            $tenantId,
            (int) $validated['partner_id'],
            (int) $validated['location_id'],
            $request->user(),
        );

        if (! empty($validated['route_id'])) {
            $so->update(['route_id' => (int) $validated['route_id']]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Sales order created.'));
    }

    public function show(SalesOrder $so): View
    {
        $configurableTemplates = ProductTemplate::with([
            'attributeLines.attribute' => fn ($q) => $q->where('active', true),
            'attributeLines.attribute.values' => fn ($q) => $q->where('active', true)->orderBy('sequence')->orderBy('value'),
        ])->whereHas('attributeLines.attribute', fn ($q) => $q->whereIn('creation_mode', ['dynamic', 'instant']))
            ->orderBy('name')->get()
            ->filter(fn ($t) => $t->attributeLines->isNotEmpty())
            ->values();

        return view('sales::orders.show', [
            'so' => $so->load(['partner', 'location', 'lines.product', 'lines.uom', 'creator']),
            'products' => Product::with('uom')->where('product_type', '!=', 'service')->orderBy('name')->get(),
            'configurableTemplates' => $configurableTemplates,
            'canConfirm' => $so->created_by !== auth()->id() && auth()->user()->can('confirm sales orders'),
        ]);
    }

    public function storeLine(Request $request, SalesOrder $so): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'uom_id' => ['required', Rule::exists('uoms', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'custom_values' => ['nullable', 'array'],
            'custom_values.*' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->salesOrders->addLine(
                $so,
                (int) $validated['product_id'],
                (int) $validated['uom_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
                $validated['custom_values'] ?? null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Line added.'));
    }

    public function configureAndAddLine(Request $request, SalesOrder $so): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_template_id' => [
                'required',
                Rule::exists('product_templates', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'attribute_value_ids' => ['required', 'array', 'min:1'],
            'attribute_value_ids.*' => [
                Rule::exists('product_attribute_values', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'uom_id' => ['required', Rule::exists('uoms', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'custom_values' => ['nullable', 'array'],
            'custom_values.*' => ['nullable', 'string', 'max:500'],
        ]);

        $template = ProductTemplate::findOrFail($validated['product_template_id']);

        try {
            $variant = $this->variantGenerator->resolveOrCreateDynamic(
                $template,
                array_map('intval', $validated['attribute_value_ids']),
            );

            $this->salesOrders->addLine(
                $so,
                $variant->id,
                (int) $validated['uom_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
                $validated['custom_values'] ?? null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['configurator' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Configured variant added.'));
    }

    public function sendQuotation(Request $request, SalesOrder $so): RedirectResponse
    {
        $validated = $request->validate([
            'validity_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $this->salesOrders->sendQuotation($so, $validated['validity_date'] ?? null);
        } catch (HttpException $e) {
            return back()->withErrors(['so' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Quotation sent to customer.'));
    }

    public function confirm(Request $request, SalesOrder $so): RedirectResponse
    {
        try {
            $this->salesOrders->confirm($so, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['so' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Sales order confirmed.'));
    }

    public function cancel(SalesOrder $so): RedirectResponse
    {
        try {
            $this->salesOrders->cancel($so);
        } catch (HttpException $e) {
            return back()->withErrors(['so' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.index')->with('status', __('Sales order cancelled.'));
    }

    public function deliver(Request $request, SalesOrderLine $line): RedirectResponse
    {
        $validated = $request->validate(['qty' => ['required', 'numeric', 'gt:0']]);

        try {
            $this->salesOrders->deliver($line, (string) $validated['qty']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $line->sales_order_id)->with('status', __('Delivered.'));
    }

    public function deliverySlip(SalesOrder $so): View
    {
        return view('sales::orders.delivery-slip', [
            'so' => $so->load(['partner', 'location', 'lines.product', 'lines.uom']),
        ]);
    }

    public function quotation(SalesOrder $so): View
    {
        return view('sales::orders.quotation', [
            'so' => $so->load(['partner', 'lines.product', 'lines.uom']),
        ]);
    }

    public function returnDelivery(Request $request, SalesOrderLine $line): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'return_location_id' => [
                'required',
                Rule::exists('locations', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('type', 'internal')),
            ],
        ]);

        try {
            $this->salesOrders->returnDelivery($line, (string) $validated['qty'], (int) $validated['return_location_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $line->sales_order_id)->with('status', __('Return recorded.'));
    }
}
