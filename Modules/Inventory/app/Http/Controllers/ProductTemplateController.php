<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeExclusion;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductTemplateAttributeLine;
use Modules\Inventory\Services\ProductDeletionService;
use Modules\Inventory\Services\VariantGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductTemplateController extends Controller
{
    public function __construct(
        private readonly VariantGeneratorService $variants,
        private readonly ProductDeletionService $deletions,
    ) {}

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
        $template->load(['attributeLines.attribute.values', 'variants']);

        $exclusions = ProductAttributeExclusion::with(['value.attribute', 'excludedValue.attribute'])
            ->where(fn ($q) => $q->whereNull('product_template_id')->orWhere('product_template_id', $template->id))
            ->get();

        return view('inventory::templates.show', [
            'template' => $template,
            'availableAttributes' => ProductAttribute::whereNotIn(
                'id',
                $template->attributeLines()->pluck('product_attribute_id'),
            )->orderBy('name')->get(),
            'exclusions' => $exclusions,
        ]);
    }

    public function storeExclusion(Request $request, ProductTemplate $template): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'product_attribute_value_id' => [
                'required',
                Rule::exists('product_attribute_values', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'excluded_value_id' => [
                'required',
                'different:product_attribute_value_id',
                Rule::exists('product_attribute_values', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        ProductAttributeExclusion::firstOrCreate([
            'tenant_id' => $tenantId,
            'product_template_id' => $template->id,
            'product_attribute_value_id' => (int) $validated['product_attribute_value_id'],
            'excluded_value_id' => (int) $validated['excluded_value_id'],
        ]);

        return back()->with('status', __('Exclusion rule added.'));
    }

    public function destroyExclusion(ProductAttributeExclusion $exclusion): RedirectResponse
    {
        $exclusion->delete();

        return back()->with('status', __('Exclusion rule removed.'));
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
        $name = $template->name;

        try {
            $this->deletions->deleteTemplate($template);
        } catch (HttpException $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()
            ->route('app.inventory.templates.index')
            ->with('status', __('Template ":name" and its variants were deleted.', ['name' => $name]));
    }
}
