<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\StockMove;

/**
 * Kit patlaması (PRD 3.17/6.0). Kit ürünün kendisi ASLA stok hareketine
 * girmez; gerçek hareketler yalnızca bileşenlere, kit miktarı × birim
 * bileşen miktarı kadar üretilir. Bir bileşen yetersizse TÜM satır tek
 * transaction'da reddedilir (kısmi patlama yok). İç içe kit yasaktır.
 */
class KitExplosionService
{
    public function __construct(private readonly StockMoveService $stockMoves) {}

    /**
     * @return array<int, StockMove>
     */
    public function explode(
        Product $kit,
        string $kitQty,
        ?int $fromLocationId,
        ?int $toLocationId,
        string $referenceType,
        int $referenceId,
    ): array {
        abort_unless($kit->is_kit, 422, __('This product is not a kit.'));

        $components = ProductKitComponent::where('kit_product_id', $kit->id)->with('componentProduct.uom')->get();

        foreach ($components as $component) {
            abort_if($component->componentProduct->is_kit, 422, __('A kit component cannot itself be a kit.'));
        }

        return DB::transaction(function () use ($components, $kitQty, $fromLocationId, $toLocationId, $referenceType, $referenceId): array {
            $moves = [];

            foreach ($components as $component) {
                $requiredQty = bcmul($component->qty, $kitQty, 4);

                $moves[] = $this->stockMoves->move(
                    tenantId: $component->tenant_id,
                    product: $component->componentProduct,
                    fromLocationId: $fromLocationId,
                    toLocationId: $toLocationId,
                    qty: $fromLocationId !== null ? bcmul($requiredQty, '-1', 4) : $requiredQty,
                    uom: $component->componentProduct->uom,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                );
            }

            return $moves;
        });
    }
}
