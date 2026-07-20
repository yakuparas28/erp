<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;

/**
 * Otomatik çıkış stratejisi (PRD 3.7): bir çıkış hareketinde lot_id açıkça
 * belirtilmemişse, lokasyonun `removal_strategy`'sine göre otomatik parti
 * seçimi yapılır. 'closest', tek bir lokasyon içinde mesafe farkı olmadığı
 * için fifo ile eşdeğer davranır (bilinçli basitleştirme).
 */
class RemovalStrategyService
{
    public function selectLot(Product $product, int $locationId): ?int
    {
        $location = Location::withoutGlobalScopes()->find($locationId);

        if ($location === null) {
            return null;
        }

        $quants = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->whereNotNull('lot_id')
            ->where('qty', '>', 0)
            ->with('lot')
            ->get();

        if ($quants->isEmpty()) {
            return null;
        }

        // Aynı saniyede oluşan kayıtlarda created_at eşitliği id ile ayrışır
        // (bkz. StockMoveService FIFO tüketimi ile aynı desen).
        $ordered = match ($location->removal_strategy) {
            'lifo' => $quants->sortBy([['created_at', 'desc'], ['id', 'desc']]),
            'fefo' => $quants->sortBy(fn (StockQuant $quant) => $quant->lot?->expiry_date ?? '9999-12-31'),
            default => $quants->sortBy([['created_at', 'asc'], ['id', 'asc']]), // fifo, closest
        };

        return $ordered->first()->lot_id;
    }
}
