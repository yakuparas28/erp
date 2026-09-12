<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\InventoryAdjustmentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdjustmentController extends Controller
{
    public function __construct(private readonly InventoryAdjustmentService $adjustments) {}

    public function index(): View
    {
        return view('inventory::adjustments.index', [
            'adjustments' => InventoryAdjustment::with(['location', 'creator'])->latest()->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        try {
            $adjustment = $this->adjustments->open($request->user()->tenant_id, $validated['location_id'], $request->user());
            $this->adjustments->startCounting($adjustment);
        } catch (HttpException $e) {
            return back()->withErrors(['location_id' => $e->getMessage()]);
        }

        activity()->causedBy($request->user())->performedOn($adjustment)->log('inventory_adjustment.opened');

        return redirect()->route('app.inventory.adjustments.show', $adjustment)->with('status', __('Stock count started; the location is now locked.'));
    }

    public function show(InventoryAdjustment $adjustment): View
    {
        $canSeeTheoretical = auth()->user()->can('approve inventory adjustments');

        return view('inventory::adjustments.show', [
            'adjustment' => $adjustment->load(['lines.product', 'location', 'creator']),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'canApprove' => $canSeeTheoretical && $adjustment->created_by !== auth()->id(),
            'showTheoretical' => $canSeeTheoretical,
        ]);
    }

    public function addCount(Request $request, InventoryAdjustment $adjustment): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $this->adjustments->addCount($adjustment, (int) $validated['product_id'], (string) $validated['qty'], null);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.adjustments.show', $adjustment)->with('status', __('Count recorded.'));
    }

    public function submit(InventoryAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->submitForApproval($adjustment);
        } catch (HttpException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.adjustments.show', $adjustment)->with('status', __('Submitted for approval.'));
    }

    public function approve(Request $request, InventoryAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->approve($adjustment, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }

        activity()->causedBy($request->user())->performedOn($adjustment)->log('inventory_adjustment.approved');

        return redirect()->route('app.inventory.adjustments.index')->with('status', __('Adjustment approved; stock updated.'));
    }

    public function cancel(InventoryAdjustment $adjustment): RedirectResponse
    {
        $this->adjustments->cancel($adjustment);

        return redirect()->route('app.inventory.adjustments.index')->with('status', __('Adjustment cancelled.'));
    }
}
