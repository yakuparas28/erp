<?php

namespace Modules\Inventory\Services\Costing;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * AVCO (PRD 3.5): her girişte ağırlıklı ortalama birim maliyet yeniden
 * hesaplanıp products.avco_unit_cost'a önbelleklenir; çıkışlar her zaman
 * güncel ortalama üzerinden değerlenir. Katman satırları yalnızca audit
 * amaçlıdır, FIFO gibi tüketilmez. Önceki miktar, `products.current_stock`
 * (fiziksel stok takibi, ayrı bir kaygı) yerine bu motorun kendi katman
 * geçmişinden türetilir — iki sistem arasında kırılgan bir bağımlılık
 * oluşmasın diye.
 */
class AvcoCostingStrategy implements CostingStrategyInterface
{
    public function recordInbound(Product $product, StockMove $move, string $qty, string $unitCost): StockValuationLayer
    {
        $currentQty = (string) StockValuationLayer::withoutGlobalScopes()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->sum('qty');
        $currentAvg = (string) ($product->avco_unit_cost ?? '0.0000');

        $currentValue = bcmul($currentQty, $currentAvg, 4);
        $incomingValue = bcmul($qty, $unitCost, 4);
        $newQty = bcadd($currentQty, $qty, 4);

        $newAvg = bccomp($newQty, '0', 4) > 0
            ? bcdiv(bcadd($currentValue, $incomingValue, 4), $newQty, 4)
            : '0.0000';

        $product->forceFill(['avco_unit_cost' => $newAvg])->save();

        $layer = new StockValuationLayer([
            'product_id' => $product->id,
            'stock_move_id' => $move->id,
            'qty' => $qty,
            'unit_cost' => $newAvg,
            'remaining_value' => bcmul($qty, $newAvg, 4),
        ]);
        $layer->tenant_id = $product->tenant_id;
        $layer->save();

        return $layer;
    }

    public function consumeOutbound(Product $product, StockMove $move, string $qty): string
    {
        $currentAvg = (string) ($product->avco_unit_cost ?? '0.0000');
        $cogs = bcmul($qty, $currentAvg, 4);

        $layer = new StockValuationLayer([
            'product_id' => $product->id,
            'stock_move_id' => $move->id,
            'qty' => bcmul($qty, '-1', 4),
            'unit_cost' => $currentAvg,
            'remaining_value' => bcmul($cogs, '-1', 4),
        ]);
        $layer->tenant_id = $product->tenant_id;
        $layer->save();

        return $cogs;
    }
}
