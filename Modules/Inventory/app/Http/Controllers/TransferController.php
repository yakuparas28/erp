<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferLine;
use Modules\Inventory\Services\WarehouseTransferService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransferController extends Controller
{
    public function __construct(private readonly WarehouseTransferService $transfers) {}

    public function index(): View
    {
        return view('inventory::transfers.index', [
            'transfers' => WarehouseTransfer::with(['fromLocation', 'toLocation', 'lines.product'])->latest()->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'from_location_id' => ['required', 'different:to_location_id', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'to_location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $transfer = WarehouseTransfer::create([
            'from_location_id' => $validated['from_location_id'],
            'to_location_id' => $validated['to_location_id'],
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        WarehouseTransferLine::create([
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'uom_id' => $product->uom_id,
            'qty' => $validated['qty'],
        ]);

        return redirect()->route('app.inventory.transfers.index')->with('status', __('Transfer created as draft.'));
    }

    public function complete(WarehouseTransfer $transfer): RedirectResponse
    {
        try {
            $this->transfers->complete($transfer);
        } catch (HttpException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        activity()->causedBy(auth()->user())->performedOn($transfer)->log('warehouse_transfer.completed');

        return redirect()->route('app.inventory.transfers.index')->with('status', __('Transfer completed; stock updated.'));
    }
}
