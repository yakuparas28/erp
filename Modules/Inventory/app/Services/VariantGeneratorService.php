<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Jobs\GenerateInstantVariants;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeExclusion;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductTemplateAttributeLine;
use Modules\Inventory\Models\ProductVariantAttributeValue;
use Modules\Inventory\Models\Uom;

/**
 * Varyant üretimi (PRD 3.17/6.1). instant → kombinasyonlar queued job ile;
 * dynamic → lazy oluşturma; never → yalnız elle. creation_mode bir template'e
 * bağlandıktan sonra kilitlenir.
 */
class VariantGeneratorService
{
    public function attachAttribute(ProductTemplate $template, ProductAttribute $attribute): ProductTemplateAttributeLine
    {
        $existing = ProductTemplateAttributeLine::where('product_template_id', $template->id)
            ->where('product_attribute_id', $attribute->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $line = new ProductTemplateAttributeLine([
            'product_template_id' => $template->id,
            'product_attribute_id' => $attribute->id,
        ]);
        $line->tenant_id = $template->tenant_id;
        $line->save();

        if ($attribute->creation_mode === 'instant') {
            GenerateInstantVariants::dispatch($template->tenant_id, $template->id);
        }

        return $line;
    }

    /**
     * @param  list<int>  $attributeValueIds
     */
    public function resolveOrCreateDynamic(ProductTemplate $template, array $attributeValueIds): Product
    {
        sort($attributeValueIds);

        abort_if(
            $this->hasExcludedCombination($template, $attributeValueIds),
            422,
            __('This combination of attribute values is excluded by a rule.'),
        );

        $existing = ProductVariantAttributeValue::whereIn('product_attribute_value_id', $attributeValueIds)
            ->whereHas('product', fn ($q) => $q->where('product_template_id', $template->id))
            ->get()
            ->groupBy('product_id')
            ->first(fn ($rows) => $rows->pluck('product_attribute_value_id')->sort()->values()->all() === $attributeValueIds);

        if ($existing !== null) {
            return Product::findOrFail($existing->first()->product_id);
        }

        $values = ProductAttributeValue::whereIn('id', $attributeValueIds)->get();
        $referenceUom = Uom::where('tenant_id', $template->tenant_id)->where('is_reference', true)->firstOrFail();

        $variant = new Product([
            'product_template_id' => $template->id,
            'uom_id' => $referenceUom->id,
            'name' => $template->name.' '.$values->pluck('value')->join(' / '),
            'product_type' => 'stockable',
            'track_by' => 'none',
            'cost_method' => 'fifo',
        ]);
        $variant->tenant_id = $template->tenant_id;
        $variant->save();

        foreach ($attributeValueIds as $valueId) {
            $link = new ProductVariantAttributeValue([
                'product_id' => $variant->id,
                'product_attribute_value_id' => $valueId,
            ]);
            $link->tenant_id = $template->tenant_id;
            $link->save();
        }

        return $variant;
    }

    /**
     * Önerilen fiyat = base_price + Σ price_extra (PRD 6.1). Satırda yine
     * de elle değiştirilebilir; burada yalnız hesaplanır, saklanmaz.
     *
     * @param  list<int>  $attributeValueIds
     */
    public function recommendedPrice(ProductTemplate $template, array $attributeValueIds): string
    {
        $extra = ProductAttributeValue::whereIn('id', $attributeValueIds)->sum('price_extra');

        return bcadd((string) $template->base_price, (string) $extra, 4);
    }

    public function updateCreationMode(ProductAttribute $attribute, string $mode): void
    {
        $attached = ProductTemplateAttributeLine::where('product_attribute_id', $attribute->id)->exists();

        abort_if($attached, 422, __('Creation mode cannot be changed after this attribute is attached to a template.'));

        $attribute->update(['creation_mode' => $mode]);
    }

    /**
     * Odoo `_exclude_from_combination` denkliği: verilen değer listesinde
     * karşılıklı olarak dışlanmış herhangi bir ikili varsa true döner.
     *
     * @param  list<int>  $valueIds
     */
    public function hasExcludedCombination(ProductTemplate $template, array $valueIds): bool
    {
        $exclusions = ProductAttributeExclusion::where('tenant_id', $template->tenant_id)
            ->where(fn ($q) => $q->whereNull('product_template_id')->orWhere('product_template_id', $template->id))
            ->get();

        foreach ($exclusions as $rule) {
            if (in_array($rule->product_attribute_value_id, $valueIds, true)
                && in_array($rule->excluded_value_id, $valueIds, true)) {
                return true;
            }
        }

        return false;
    }
}
