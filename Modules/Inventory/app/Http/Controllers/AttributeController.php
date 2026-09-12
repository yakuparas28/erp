<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplateAttributeLine;
use Modules\Inventory\Models\ProductVariantAttributeValue;

class AttributeController extends Controller
{
    public function index(): View
    {
        return view('inventory::attributes.index', [
            'attributes' => ProductAttribute::with(['values' => fn ($q) => $q->orderBy('sequence')->orderBy('value')])
                ->orderBy('sequence')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'creation_mode' => ['required', 'in:instant,dynamic,never'],
            'display_type' => ['required', 'in:select,radio,pill,color'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        ProductAttribute::create($validated + ['active' => true]);

        return back()->with('status', __('Attribute added.'));
    }

    public function update(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_type' => ['required', 'in:select,radio,pill,color'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $attribute->update($validated);

        return back()->with('status', __('Attribute updated.'));
    }

    public function archive(ProductAttribute $attribute): RedirectResponse
    {
        $attribute->update(['active' => false]);

        return back()->with('status', __('Attribute archived.'));
    }

    public function restore(ProductAttribute $attribute): RedirectResponse
    {
        $attribute->update(['active' => true]);

        return back()->with('status', __('Attribute restored.'));
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        $isAttached = ProductTemplateAttributeLine::where('product_attribute_id', $attribute->id)->exists();

        if ($isAttached) {
            return back()->withErrors(['attribute' => __('This attribute is attached to a template and cannot be deleted.')]);
        }

        $attribute->values()->delete();
        $attribute->delete();

        return back()->with('status', __('Attribute deleted.'));
    }

    public function storeValue(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'price_extra' => ['nullable', 'numeric'],
            'html_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'is_custom' => ['sometimes', 'boolean'],
        ]);

        $attribute->values()->create([
            'tenant_id' => $attribute->tenant_id,
            'value' => $validated['value'],
            'price_extra' => $validated['price_extra'] ?? '0',
            'html_color' => $validated['html_color'] ?? null,
            'sequence' => $validated['sequence'] ?? 0,
            'is_custom' => (bool) ($validated['is_custom'] ?? false),
            'active' => true,
        ]);

        return back()->with('status', __('Value added.'));
    }

    public function updateValue(Request $request, ProductAttributeValue $value): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'price_extra' => ['nullable', 'numeric'],
            'html_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'is_custom' => ['sometimes', 'boolean'],
        ]);

        $value->update($validated + ['is_custom' => (bool) ($validated['is_custom'] ?? false)]);

        return back()->with('status', __('Value updated.'));
    }

    public function destroyValue(ProductAttributeValue $value): RedirectResponse
    {
        $isUsedByVariant = ProductVariantAttributeValue::where('product_attribute_value_id', $value->id)->exists();

        if ($isUsedByVariant) {
            return back()->withErrors(['value' => __('This attribute value is used by an existing variant and cannot be deleted.')]);
        }

        if ($value->image_path && Storage::disk('public')->exists($value->image_path)) {
            Storage::disk('public')->delete($value->image_path);
        }

        $value->delete();

        return back()->with('status', __('Value deleted.'));
    }

    public function uploadValueImage(Request $request, ProductAttributeValue $value): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        if ($value->image_path && Storage::disk('public')->exists($value->image_path)) {
            Storage::disk('public')->delete($value->image_path);
        }

        $path = $request->file('image')->store('attribute-values', 'public');
        $value->update(['image_path' => $path]);

        return back()->with('status', __('Image uploaded.'));
    }

    public function destroyValueImage(ProductAttributeValue $value): RedirectResponse
    {
        if ($value->image_path && Storage::disk('public')->exists($value->image_path)) {
            Storage::disk('public')->delete($value->image_path);
        }

        $value->update(['image_path' => null]);

        return back()->with('status', __('Image removed.'));
    }

    public function reorder(Request $request, ProductAttribute $attribute, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $siblings = ProductAttribute::orderBy('sequence')->orderBy('id')->get();
        $currentIndex = $siblings->search(fn ($a) => $a->id === $attribute->id);

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            return back();
        }

        $target = $siblings[$targetIndex];
        [$attribute->sequence, $target->sequence] = [$target->sequence, $attribute->sequence];

        if ($attribute->sequence === $target->sequence) {
            $attribute->sequence = $currentIndex;
            $target->sequence = $targetIndex;
        }

        $attribute->save();
        $target->save();

        return back();
    }

    public function reorderValue(Request $request, ProductAttributeValue $value, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $siblings = ProductAttributeValue::where('product_attribute_id', $value->product_attribute_id)
            ->orderBy('sequence')->orderBy('id')->get();
        $currentIndex = $siblings->search(fn ($v) => $v->id === $value->id);

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            return back();
        }

        $target = $siblings[$targetIndex];
        [$value->sequence, $target->sequence] = [$target->sequence, $value->sequence];

        if ($value->sequence === $target->sequence) {
            $value->sequence = $currentIndex;
            $target->sequence = $targetIndex;
        }

        $value->save();
        $target->save();

        return back();
    }
}
