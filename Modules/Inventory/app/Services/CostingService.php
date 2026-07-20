<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Services\Costing\AvcoCostingStrategy;
use Modules\Inventory\Services\Costing\CostingStrategyInterface;
use Modules\Inventory\Services\Costing\FifoCostingStrategy;
use Modules\Inventory\Services\Costing\StandardCostingStrategy;

/**
 * products.cost_method'a göre doğru maliyet stratejisine delege eden
 * merkezi giriş noktası (PRD 3.5). StockMoveService'e otomatik bağlı
 * DEĞİLDİR: transferler değer yaratmaz/tüketmez; yalnızca gerçek
 * giriş/çıkış üreten çağrıcılar (ileride PO/SO) bu servisi açıkça çağırır.
 */
class CostingService
{
    public function recordInbound(Product $product, StockMove $move, string $qty, string $unitCost): StockValuationLayer
    {
        return $this->strategyFor($product)->recordInbound($product, $move, $qty, $unitCost);
    }

    public function consumeOutbound(Product $product, StockMove $move, string $qty): string
    {
        return $this->strategyFor($product)->consumeOutbound($product, $move, $qty);
    }

    private function strategyFor(Product $product): CostingStrategyInterface
    {
        return match ($product->cost_method) {
            'fifo' => new FifoCostingStrategy,
            'avco' => new AvcoCostingStrategy,
            'standard' => new StandardCostingStrategy,
        };
    }
}
