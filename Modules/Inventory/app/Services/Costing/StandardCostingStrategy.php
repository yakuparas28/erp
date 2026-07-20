<?php

namespace Modules\Inventory\Services\Costing;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * Standart Sabit Maliyet (PRD 3.5): products.standard_cost sabittir; giriş/
 * çıkış hareketleri her zaman bu değeri kullanır. Fiili alım fiyatındaki
 * sapmalar bu fazda yalnızca kayıt altına alınır (muhasebe entegrasyonu
 * Faz 9); burada herhangi bir sapma hesabı işlenmez.
 */
class StandardCostingStrategy implements CostingStrategyInterface
{
    public function recordInbound(Product $product, StockMove $move, string $qty, string $unitCost): StockValuationLayer
    {
        $fixedCost = (string) $product->standard_cost;

        $layer = new StockValuationLayer([
            'product_id' => $product->id,
            'stock_move_id' => $move->id,
            'qty' => $qty,
            'unit_cost' => $fixedCost,
            'remaining_value' => bcmul($qty, $fixedCost, 4),
        ]);
        $layer->tenant_id = $product->tenant_id;
        $layer->save();

        return $layer;
    }

    public function consumeOutbound(Product $product, StockMove $move, string $qty): string
    {
        $fixedCost = (string) $product->standard_cost;
        $cogs = bcmul($qty, $fixedCost, 4);

        $layer = new StockValuationLayer([
            'product_id' => $product->id,
            'stock_move_id' => $move->id,
            'qty' => bcmul($qty, '-1', 4),
            'unit_cost' => $fixedCost,
            'remaining_value' => bcmul($cogs, '-1', 4),
        ]);
        $layer->tenant_id = $product->tenant_id;
        $layer->save();

        return $cogs;
    }
}
