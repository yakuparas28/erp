<?php

namespace Modules\Purchase\Listeners;

use Modules\Inventory\Events\ReplenishmentAcknowledged;
use Modules\Inventory\Models\Product;
use Modules\Purchase\Services\PurchaseOrderService;

/**
 * Bir yeniden sipariş önerisi onaylandığında (PRD 3.8), ürünün varsayılan
 * tedarikçisine otomatik bir draft PO + satır üretir. Varsayılan tedarikçi
 * tanımlı değilse sessizce hiçbir şey yapılmaz (öneri raporda kalır).
 */
class CreateDraftPurchaseOrderFromReplenishment
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrders) {}

    public function handle(ReplenishmentAcknowledged $event): void
    {
        $rule = $event->suggestion->reorderingRule;
        $product = Product::withoutGlobalScopes()->find($rule->product_id);

        if ($product === null || $product->default_supplier_id === null) {
            return;
        }

        $po = $this->purchaseOrders->create(
            $product->tenant_id,
            $product->default_supplier_id,
            $event->acknowledgedBy,
        );

        $this->purchaseOrders->addLine(
            $po,
            $product->id,
            $product->uom_id,
            (string) $event->suggestion->suggested_qty,
            (string) ($product->standard_cost ?? '0.0000'),
        );
    }
}
