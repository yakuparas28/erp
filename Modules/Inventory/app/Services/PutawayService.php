<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PutawayRule;

/**
 * Otomatik yerleşim (PRD 3.7): bir ürün belirli bir kaynak lokasyona
 * girdiğinde, sequence sırasına göre ilk eşleşen kural (ürüne özel VEYA
 * kategori bazlı, ikisi aynı havuzda sequence'e göre yarışır) bir hedef
 * lokasyon önerir.
 */
class PutawayService
{
    public function resolveDestination(Product $product, int $sourceLocationId): ?int
    {
        $rule = PutawayRule::withoutGlobalScopes()
            ->where('tenant_id', $product->tenant_id)
            ->where('source_location_id', $sourceLocationId)
            ->where(function ($query) use ($product): void {
                $query->where('product_id', $product->id)
                    ->orWhere(function ($q) use ($product): void {
                        $q->whereNull('product_id')
                            ->where('product_category_id', $product->product_category_id);
                    });
            })
            ->orderBy('sequence')
            ->first();

        return $rule?->dest_location_id;
    }
}
