<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\ProductDeletionService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductController extends Controller
{
    public function __construct(private readonly ProductDeletionService $deletions) {}

    public function index(): View
    {
        $products = Product::with(['uom', 'category', 'kitComponents.componentProduct'])->orderBy('name')->get();

        return view('inventory::products.index', [
            // Varyantlı ürünler tek tek değil, şablonları altında gruplanmış tek satır olarak listelenir.
            'standaloneProducts' => $products->whereNull('product_template_id')->values(),
            'templates' => ProductTemplate::withCount('variants')->has('variants')->with('variants.uom')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'uoms' => Uom::where('is_reference', true)->orderBy('name')->get(),
            'nonKitProducts' => $products->where('is_kit', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['is_kit'] = $request->boolean('is_kit');

        $product = Product::create($validated);

        activity()->causedBy($request->user())->performedOn($product)->log('product.created');

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name added.', ['name' => $product->name]));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['is_kit'] = $request->boolean('is_kit');

        $product->update($validated);

        activity()->causedBy($request->user())->performedOn($product)->log('product.updated');

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name updated.', ['name' => $product->name]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->deletions->delete($product);
        } catch (HttpException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name deleted.', ['name' => $product->name]));
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
            'is_kit' => ['sometimes', 'boolean'],
        ]);
    }
}
