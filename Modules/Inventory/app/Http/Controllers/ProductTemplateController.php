<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Services\VariantGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductTemplateController extends Controller
{
    public function __construct(private readonly VariantGeneratorService $variants) {}

    public function index(): View
    {
        return view('inventory::templates.index', [
            'templates' => ProductTemplate::withCount('variants')->orderBy('name')->get(),
            'attributes' => ProductAttribute::with('values')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $template = ProductTemplate::create($validated);

        return redirect()->route('app.inventory.templates.show', $template)->with('status', __(':name added.', ['name' => $template->name]));
    }

    public function show(ProductTemplate $template): View
    {
        return view('inventory::templates.show', [
            'template' => $template->load(['attributeLines.attribute.values', 'variants']),
            'availableAttributes' => ProductAttribute::whereNotIn(
                'id',
                $template->attributeLines()->pluck('product_attribute_id'),
            )->orderBy('name')->get(),
        ]);
    }

    public function storeAttribute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'creation_mode' => ['required', 'in:instant,dynamic,never'],
        ]);

        ProductAttribute::create($validated);

        return redirect()->route('app.inventory.templates.index')->with('status', __('Attribute added.'));
    }

    public function storeAttributeValue(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'price_extra' => ['nullable', 'numeric'],
        ]);

        ProductAttributeValue::create([
            'product_attribute_id' => $attribute->id,
            'value' => $validated['value'],
            'price_extra' => $validated['price_extra'] ?? 0,
        ]);

        return redirect()->route('app.inventory.templates.index')->with('status', __('Attribute value added.'));
    }

    public function attachAttribute(Request $request, ProductTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'product_attribute_id' => ['required', 'exists:product_attributes,id'],
        ]);

        $attribute = ProductAttribute::findOrFail($validated['product_attribute_id']);

        try {
            $this->variants->attachAttribute($template, $attribute);
        } catch (HttpException $e) {
            return back()->withErrors(['product_attribute_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('app.inventory.templates.show', $template)
            ->with('status', __('Attribute attached; variants are being generated.'));
    }
}
