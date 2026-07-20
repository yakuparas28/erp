<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route;
use Modules\Inventory\Models\Uom;

/**
 * Rotalar (PRD 3.7): bir route sıralı route_rules'tan oluşur. executePush
 * yalnızca action=push adımlarını sırayla zincirleyerek çok adımlı bir
 * sevkiyat/transfer akışı üretir (ör. 'İki Adımlı Teslimat'). action=pull
 * adımları bu fazda otomatik çalışmaz — yalnızca veri olarak durur, talep
 * (Faz 7/8'de satış/satınalma) tetiklediğinde işlenecektir.
 */
class RouteService
{
    public function __construct(private readonly StockMoveService $stockMoves) {}

    public function executePush(Route $route, Product $product, string $qty, Uom $uom): void
    {
        $rules = $route->rules()->where('action', 'push')->orderBy('sequence')->get();

        foreach ($rules as $rule) {
            $this->stockMoves->move(
                tenantId: $route->tenant_id,
                product: $product,
                fromLocationId: $rule->from_location_id,
                toLocationId: $rule->to_location_id,
                qty: bcmul($qty, '-1', 4),
                uom: $uom,
                referenceType: 'route_rule',
                referenceId: $rule->id,
            );

            $this->stockMoves->move(
                tenantId: $route->tenant_id,
                product: $product,
                fromLocationId: $rule->from_location_id,
                toLocationId: $rule->to_location_id,
                qty: $qty,
                uom: $uom,
                referenceType: 'route_rule',
                referenceId: $rule->id,
            );
        }
    }
}
