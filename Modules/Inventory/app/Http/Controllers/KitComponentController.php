<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KitComponentController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'component_product_id' => ['required', 'exists:products,id', 'different:product'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            ProductKitComponent::create([
                'kit_product_id' => $product->id,
                'component_product_id' => $validated['component_product_id'],
                'qty' => $validated['qty'],
            ]);
        } catch (HttpException $e) {
            return back()->withErrors(['component_product_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __('Kit component added.'));
    }

    public function destroy(ProductKitComponent $component): RedirectResponse
    {
        $component->delete();

        return redirect()
            ->route('app.inventory.products.index')
            ->with('status', __('Kit component removed.'));
    }
}
