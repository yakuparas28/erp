<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrders) {}

    public function index(): View
    {
        return view('purchase::orders.index', [
            'orders' => PurchaseOrder::with(['partner', 'lines', 'creator'])->latest()->get(),
            'suppliers' => Partner::where('is_supplier', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['partner_id' => ['required', 'exists:partners,id']]);

        $po = $this->purchaseOrders->create($request->user()->tenant_id, (int) $validated['partner_id'], $request->user());

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Purchase order created.'));
    }

    public function show(PurchaseOrder $po): View
    {
        return view('purchase::orders.show', [
            'po' => $po->load(['partner', 'lines.product', 'lines.uom', 'creator']),
            'products' => Product::with('uom')->where('product_type', '!=', 'service')->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'canConfirm' => $po->created_by !== auth()->id() && auth()->user()->can('confirm purchase orders'),
        ]);
    }

    public function storeLine(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->purchaseOrders->addLine(
                $po,
                (int) $validated['product_id'],
                (int) $validated['uom_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Line added.'));
    }

    public function sendRfq(PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->sendRfq($po);
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Sent as RFQ.'));
    }

    public function confirm(Request $request, PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->confirm($po, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Purchase order confirmed.'));
    }

    public function cancel(PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->cancel($po);
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.index')->with('status', __('Purchase order cancelled.'));
    }

    public function receive(Request $request, PurchaseOrderLine $line): RedirectResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'receiving_location_id' => ['required', 'exists:locations,id'],
        ]);

        try {
            $this->purchaseOrders->receive($line, (string) $validated['qty'], (int) $validated['receiving_location_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $line->purchase_order_id)->with('status', __('Received.'));
    }

    public function returnReceipt(Request $request, PurchaseOrderLine $line): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'from_location_id' => [
                'required',
                Rule::exists('locations', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('type', 'internal')),
            ],
        ]);

        try {
            $this->purchaseOrders->returnReceipt($line, (string) $validated['qty'], (int) $validated['from_location_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $line->purchase_order_id)->with('status', __('Return recorded.'));
    }
}
