<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\Scrap;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\ScrapService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScrapController extends Controller
{
    public function __construct(private readonly ScrapService $scraps) {}

    public function index(): View
    {
        return view('inventory::scraps.index', [
            'scraps' => Scrap::with(['product', 'sourceLocation', 'scrapLocation', 'uom', 'lot', 'doneBy'])
                ->orderByDesc('scrapped_at')->get(),
            'products' => Product::with('uom')->where('product_type', '!=', 'service')->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'lots' => ProductLot::orderBy('lot_number')->get(),
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
            'source_location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('type', 'internal')),
            ],
            'uom_id' => [
                'required',
                Rule::exists('uoms', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
            'lot_id' => [
                'nullable',
                Rule::exists('product_lots', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $uom = Uom::findOrFail($validated['uom_id']);

        try {
            $this->scraps->scrap(
                $tenantId,
                $product,
                (int) $validated['source_location_id'],
                $uom,
                (string) $validated['qty'],
                isset($validated['lot_id']) ? (int) $validated['lot_id'] : null,
                $validated['reason'] ?? null,
                $request->user(),
            );
        } catch (HttpException $e) {
            return back()->withErrors(['scrap' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.scraps.index')->with('status', __('Product scrapped.'));
    }
}
