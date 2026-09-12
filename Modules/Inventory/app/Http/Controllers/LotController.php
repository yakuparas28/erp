<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;

class LotController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProductLot::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->boolean('expired_only')) {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
        }

        $lots = $query->orderByDesc('id')->get();

        $lotStocks = StockQuant::selectRaw('lot_id, SUM(qty) as total')
            ->whereIn('lot_id', $lots->pluck('id'))
            ->groupBy('lot_id')
            ->pluck('total', 'lot_id');

        return view('inventory::lots.index', [
            'lots' => $lots,
            'lotStocks' => $lotStocks,
            'products' => Product::where('track_by', '!=', 'none')->orderBy('name')->get(),
            'filterProductId' => $request->integer('product_id'),
            'filterExpiredOnly' => $request->boolean('expired_only'),
        ]);
    }

    public function trace(ProductLot $lot): View
    {
        $moves = StockMove::where('lot_id', $lot->id)
            ->with(['product', 'fromLocation', 'toLocation'])
            ->orderBy('created_at')
            ->get();

        return view('inventory::lots.trace', [
            'lot' => $lot->load('product'),
            'inbound' => $moves->where('qty', '>', 0)->values(),
            'outbound' => $moves->where('qty', '<', 0)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'lot_number' => ['required', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $exists = ProductLot::where('tenant_id', $tenantId)
            ->where('product_id', $validated['product_id'])
            ->where('lot_number', $validated['lot_number'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['lot_number' => __('This lot number is already used for this product.')]);
        }

        ProductLot::create($validated);

        return redirect()->route('app.inventory.lots.index')->with('status', __('Lot added.'));
    }

    public function update(Request $request, ProductLot $lot): RedirectResponse
    {
        $validated = $request->validate([
            'expiry_date' => ['nullable', 'date'],
        ]);

        $lot->update($validated);

        return redirect()->route('app.inventory.lots.index')->with('status', __('Lot updated.'));
    }

    public function destroy(ProductLot $lot): RedirectResponse
    {
        if (StockMove::where('lot_id', $lot->id)->exists()) {
            return back()->withErrors(['lot' => __('This lot has stock moves and cannot be deleted.')]);
        }

        if (StockQuant::where('lot_id', $lot->id)->where('qty', '>', 0)->exists()) {
            return back()->withErrors(['lot' => __('This lot has stock on hand and cannot be deleted.')]);
        }

        $lot->delete();

        return redirect()->route('app.inventory.lots.index')->with('status', __('Lot deleted.'));
    }
}
