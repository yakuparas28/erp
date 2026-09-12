<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;

class UomController extends Controller
{
    public function index(): View
    {
        return view('inventory::uoms.index', [
            'categories' => UomCategory::with('uoms')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        UomCategory::create($validated);

        return back()->with('status', __('Unit category added.'));
    }

    public function destroyCategory(UomCategory $category): RedirectResponse
    {
        if ($category->uoms()->exists()) {
            return back()->withErrors(['category' => __('Delete the units in this category first.')]);
        }

        $category->delete();

        return back()->with('status', __('Unit category deleted.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'uom_category_id' => [
                'required',
                Rule::exists('uom_categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'factor' => ['required', 'numeric', 'gt:0'],
            'is_reference' => ['sometimes', 'boolean'],
        ]);

        $isReference = (bool) ($validated['is_reference'] ?? false);

        if ($isReference) {
            $existingReference = Uom::where('uom_category_id', $validated['uom_category_id'])
                ->where('is_reference', true)->exists();

            if ($existingReference) {
                return back()->withErrors(['is_reference' => __('This category already has a reference unit.')]);
            }
        }

        Uom::create([
            'uom_category_id' => $validated['uom_category_id'],
            'name' => $validated['name'],
            'factor' => $validated['factor'],
            'is_reference' => $isReference,
        ]);

        return back()->with('status', __('Unit added.'));
    }

    public function update(Request $request, Uom $uom): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'factor' => ['required', 'numeric', 'gt:0'],
        ]);

        $uom->update($validated);

        return back()->with('status', __('Unit updated.'));
    }

    public function destroy(Uom $uom): RedirectResponse
    {
        if (Product::where('uom_id', $uom->id)->exists()) {
            return back()->withErrors(['uom' => __('This unit is used by products and cannot be deleted.')]);
        }

        if ($uom->is_reference) {
            return back()->withErrors(['uom' => __('Reference units cannot be deleted; delete the category instead.')]);
        }

        $uom->delete();

        return back()->with('status', __('Unit deleted.'));
    }
}
