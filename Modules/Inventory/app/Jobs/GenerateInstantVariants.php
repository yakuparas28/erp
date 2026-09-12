<?php

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttributeExclusion;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductVariantAttributeValue;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;

/**
 * Instant modda bir attribute template'e bağlandığında, o anda template'e
 * bağlı TÜM instant attribute'ların değerlerinin kartezyen çarpımı kadar
 * varyant (products satırı) üretir. Zaten var olan kombinasyonlar atlanır
 * (idempotent) — PRD 3.17 kombinasyon patlaması bir queued job ile yapılır.
 */
class GenerateInstantVariants implements ShouldQueue
{
    use InteractsWithQueue, Queueable, QueueableTrait, SerializesModels;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $templateId,
    ) {}

    public function handle(): void
    {
        $template = ProductTemplate::withoutGlobalScopes()->findOrFail($this->templateId);

        $instantValueGroups = $template->attributeLines()
            ->withoutGlobalScopes()
            ->with('attribute.values')
            ->get()
            ->filter(fn ($line) => $line->attribute->creation_mode === 'instant')
            ->map(fn ($line) => $line->attribute->values)
            ->filter(fn ($values) => $values->isNotEmpty())
            ->values();

        if ($instantValueGroups->isEmpty()) {
            return;
        }

        $this->pruneStalePartialVariants($template, $instantValueGroups->count());

        $combinations = $instantValueGroups->reduce(
            fn (array $carry, $values) => collect($carry)
                ->crossJoin($values->all())
                ->map(fn ($pair) => array_merge((array) $pair[0], [$pair[1]]))
                ->all(),
            [[]],
        );

        $exclusions = ProductAttributeExclusion::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('product_template_id')->orWhere('product_template_id', $template->id))
            ->get()
            ->flatMap(fn ($e) => [
                $e->product_attribute_value_id.':'.$e->excluded_value_id,
                $e->excluded_value_id.':'.$e->product_attribute_value_id,
            ])
            ->flip();

        $combinations = array_values(array_filter($combinations, function (array $combination) use ($exclusions): bool {
            $ids = collect($combination)->pluck('id')->all();

            foreach ($ids as $a) {
                foreach ($ids as $b) {
                    if ($a !== $b && $exclusions->has($a.':'.$b)) {
                        return false;
                    }
                }
            }

            return true;
        }));

        $referenceUom = Uom::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('is_reference', true)
            ->firstOrFail();

        foreach ($combinations as $combination) {
            $valueIds = collect($combination)->pluck('id')->sort()->values()->all();

            $existing = ProductVariantAttributeValue::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->whereIn('product_attribute_value_id', $valueIds)
                ->get()
                ->groupBy('product_id')
                ->first(fn ($rows) => $rows->pluck('product_attribute_value_id')->sort()->values()->all() === $valueIds);

            if ($existing !== null) {
                continue;
            }

            $name = $template->name.' '.collect($combination)->pluck('value')->join(' / ');

            $variant = new Product([
                'product_template_id' => $template->id,
                'uom_id' => $referenceUom->id,
                'name' => $name,
                'product_type' => 'stockable',
                'track_by' => 'none',
                'cost_method' => 'fifo',
            ]);
            $variant->tenant_id = $this->tenantId;
            $variant->save();

            foreach ($valueIds as $valueId) {
                $link = new ProductVariantAttributeValue([
                    'product_id' => $variant->id,
                    'product_attribute_value_id' => $valueId,
                ]);
                $link->tenant_id = $this->tenantId;
                $link->save();
            }
        }
    }

    /**
     * İkinci (veya sonraki) bir instant attribute eklendiğinde, önceki
     * eksik-boyutlu (kısmi) varyantlar artık geçerli bir kombinasyon
     * temsil etmez. Stoksuz ve hareketsiz olanlar güvenle silinir; stok
     * hareketi görmüş bir varyant asla otomatik silinmez.
     */
    private function pruneStalePartialVariants(ProductTemplate $template, int $expectedValueCount): void
    {
        $variants = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('product_template_id', $template->id)
            ->get();

        foreach ($variants as $variant) {
            $valueCount = ProductVariantAttributeValue::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('product_id', $variant->id)
                ->count();

            if ($valueCount >= $expectedValueCount) {
                continue;
            }

            $hasStock = StockQuant::withoutGlobalScopes()->where('product_id', $variant->id)->where('qty', '<>', 0)->exists()
                || StockMove::withoutGlobalScopes()->where('product_id', $variant->id)->exists();

            if ($hasStock) {
                continue;
            }

            ProductVariantAttributeValue::withoutGlobalScopes()->where('product_id', $variant->id)->delete();
            $variant->delete();
        }
    }
}
