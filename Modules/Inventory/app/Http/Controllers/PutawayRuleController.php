<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\PutawayRule;

class PutawayRuleController extends Controller
{
    public function index(): View
    {
        return view('inventory::putaway.index', [
            'rules' => PutawayRule::with(['product', 'productCategory', 'sourceLocation', 'destLocation'])->orderBy('sequence')->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'dest_location_id' => ['required', 'exists:locations,id', 'different:source_location_id'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        PutawayRule::create($validated);

        return redirect()->route('app.inventory.putaway.index')->with('status', __('Putaway rule added.'));
    }

    public function destroy(PutawayRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('app.inventory.putaway.index')->with('status', __('Putaway rule deleted.'));
    }
}
