<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __(':name deleted.', ['name' => $product->name]));
    }

    public function uploadImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $path = $request->file('image')->store('products', 'public');
        $product->update(['image_path' => $path]);

        return back()->with('status', __('Image uploaded.'));
    }

    public function destroyImage(Product $product): RedirectResponse
    {
        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->update(['image_path' => null]);

        return back()->with('status', __('Image removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;
        $productId = $request->route('product')?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($productId),
            ],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'product_type' => ['required', 'in:stockable,consumable,service'],
            'track_by' => ['required', 'in:none,lot,serial'],
            'reservation_method' => ['nullable', 'in:at_confirmation,manual'],
            'list_price' => ['nullable', 'numeric', 'min:0'],
            'sale_ok' => ['sometimes', 'boolean'],
            'purchase_ok' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_sale' => ['nullable', 'string', 'max:2000'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'country_of_origin' => ['nullable', 'string', 'size:2'],
            'is_kit' => ['sometimes', 'boolean'],
        ]);
    }
}
