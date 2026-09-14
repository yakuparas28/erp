<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\ProductBrand;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('inventory::brands.index', [
            'brands' => ProductBrand::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);
        ProductBrand::create($validated);

        return redirect()->route('app.inventory.brands.index')->with('status', __('Brand added.'));
    }

    public function update(Request $request, ProductBrand $brand): RedirectResponse
    {
        $validated = $this->validated($request, $brand);
        $brand->update($validated);

        return redirect()->route('app.inventory.brands.index')->with('status', __('Brand updated.'));
    }

    public function destroy(ProductBrand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->withErrors(['brand' => __('This brand is used by products and cannot be deleted.')]);
        }
        $brand->delete();

        return redirect()->route('app.inventory.brands.index')->with('status', __('Brand deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProductBrand $brand): array
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:128',
                Rule::unique('product_brands', 'name')
                    ->ignore($brand?->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
