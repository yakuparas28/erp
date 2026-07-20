<?php

namespace Modules\Inventory\Services\Costing;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * FIFO (PRD 3.5): her giriş ayrı bir katman; çıkışta en eski katmandan
 * başlanarak tüketim yapılır. Katman satırları `lockForUpdate()` ile
 * kilitlenip tek transaction içinde güncellenir (eşzamanlı çift tüketim
 * riskine karşı).
 */
class FifoCostingStrategy implements CostingStrategyInterface
{
    public function recordInbound(Product $product, StockMove $move, string $qty, string $unitCost): StockValuationLayer
    {
        $layer = new StockValuationLayer([
            'product_id' => $product->id,
            'stock_move_id' => $move->id,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'remaining_value' => bcmul($qty, $unitCost, 4),
        ]);
        $layer->tenant_id = $product->tenant_id;
        $layer->save();

        return $layer;
    }

    public function consumeOutbound(Product $product, StockMove $move, string $qty): string
    {
        return DB::transaction(function () use ($product, $qty): string {
            $remainingToConsume = $qty;
            $totalCogs = '0.0000';

            $layers = StockValuationLayer::withoutGlobalScopes()
                ->where('tenant_id', $product->tenant_id)
                ->where('product_id', $product->id)
                ->where('qty', '>', 0)
                ->where('remaining_value', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($layers as $layer) {
                if (bccomp($remainingToConsume, '0', 4) <= 0) {
                    break;
                }

                $availableInLayer = bccomp($layer->unit_cost, '0', 4) > 0
                    ? bcdiv($layer->remaining_value, $layer->unit_cost, 4)
                    : '0.0000';

                $consumeFromLayer = bccomp($availableInLayer, $remainingToConsume, 4) < 0
                    ? $availableInLayer
                    : $remainingToConsume;

                $consumedValue = bcmul($consumeFromLayer, $layer->unit_cost, 4);

                $layer->update([
                    'remaining_value' => bcsub($layer->remaining_value, $consumedValue, 4),
                ]);

                $totalCogs = bcadd($totalCogs, $consumedValue, 4);
                $remainingToConsume = bcsub($remainingToConsume, $consumeFromLayer, 4);
            }

            return $totalCogs;
        });
    }
}
