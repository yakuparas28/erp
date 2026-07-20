<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductTemplateAttributeLine;
use Modules\Inventory\Models\ProductVariantAttributeValue;
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
            'attachedAttributeIds' => ProductTemplateAttributeLine::pluck('product_attribute_id')->all(),
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

    public function destroy(ProductTemplate $template): RedirectResponse
    {
        if ($template->variants()->exists()) {
            return back()->withErrors([
                'template' => __('This template has generated variants; remove them first before deleting the template.'),
            ]);
        }

        $template->attributeLines()->delete();
        $template->delete();

        return redirect()
            ->route('app.inventory.templates.index')
            ->with('status', __('Template ":name" deleted.', ['name' => $template->name]));
    }

    public function destroyAttribute(ProductAttribute $attribute): RedirectResponse
    {
        $isAttached = ProductTemplateAttributeLine::where('product_attribute_id', $attribute->id)->exists();

        if ($isAttached) {
            return back()->withErrors([
                'attribute' => __('This attribute is attached to a template and cannot be deleted.'),
            ]);
        }

        ProductAttributeValue::where('product_attribute_id', $attribute->id)->delete();
        $attribute->delete();

        return redirect()
            ->route('app.inventory.templates.index')
            ->with('status', __('Attribute ":name" deleted.', ['name' => $attribute->name]));
    }

    public function destroyAttributeValue(ProductAttributeValue $value): RedirectResponse
    {
        $isUsedByVariant = ProductVariantAttributeValue::where('product_attribute_value_id', $value->id)->exists();

        if ($isUsedByVariant) {
            return back()->withErrors([
                'value' => __('This value is used by a generated variant and cannot be deleted.'),
            ]);
        }

        $value->delete();

        return redirect()
            ->route('app.inventory.templates.index')
            ->with('status', __('Value ":name" deleted.', ['name' => $value->value]));
    }
}
