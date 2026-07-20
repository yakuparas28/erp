<?php

namespace Modules\Inventory\Services\Costing;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * Maliyet motoru stratejisi (PRD 3.5, Strategy Pattern). recordInbound bir
 * giriş hareketi için katman oluşturur; consumeOutbound bir çıkış hareketi
 * için tüketilen değeri (COGS) hesaplayıp döner.
 */
interface CostingStrategyInterface
{
    public function recordInbound(Product $product, StockMove $move, string $qty, string $unitCost): StockValuationLayer;

    public function consumeOutbound(Product $product, StockMove $move, string $qty): string;
}
