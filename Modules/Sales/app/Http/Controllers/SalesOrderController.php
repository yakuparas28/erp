<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function index(): View
    {
        return view('sales::orders.index', [
            'orders' => SalesOrder::with(['partner', 'location', 'lines', 'creator'])->latest()->get(),
            'customers' => Partner::where('is_customer', true)->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $so = $this->salesOrders->create(
            $request->user()->tenant_id,
            (int) $validated['partner_id'],
            (int) $validated['location_id'],
            $request->user(),
        );

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Sales order created.'));
    }

    public function show(SalesOrder $so): View
    {
        return view('sales::orders.show', [
            'so' => $so->load(['partner', 'location', 'lines.product', 'lines.uom', 'creator']),
            'products' => Product::with('uom')->where('product_type', '!=', 'service')->orderBy('name')->get(),
            'canConfirm' => $so->created_by !== auth()->id() && auth()->user()->can('confirm sales orders'),
        ]);
    }

    public function storeLine(Request $request, SalesOrder $so): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->salesOrders->addLine(
                $so,
                (int) $validated['product_id'],
                (int) $validated['uom_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Line added.'));
    }

    public function sendQuotation(SalesOrder $so): RedirectResponse
    {
        try {
            $this->salesOrders->sendQuotation($so);
        } catch (HttpException $e) {
            return back()->withErrors(['so' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Sent as quotation.'));
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
}
