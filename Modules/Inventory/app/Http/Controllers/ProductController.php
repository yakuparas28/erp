<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('inventory::products.index', [
            'products' => Product::with(['uom', 'category'])->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'uoms' => Uom::where('is_reference', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $product = Product::create($validated);

        activity()->causedBy($request->user())->performedOn($product)->log('product.created');

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name added.', ['name' => $product->name]));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request));

        activity()->causedBy($request->user())->performedOn($product)->log('product.updated');

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name updated.', ['name' => $product->name]));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        ProductCategory::create($validated);

        return redirect()->route('app.inventory.products.index')->with('status', __('Category added.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'product_type' => ['required', 'in:stockable,consumable,service'],
            'track_by' => ['required', 'in:none,lot,serial'],
        ]);
    }
}
