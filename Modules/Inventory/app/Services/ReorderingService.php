<?php

namespace Modules\Inventory\Services;

use App\Models\User;
use Modules\Inventory\Events\ReplenishmentAcknowledged;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Models\StockQuant;

/**
 * Otomatik yeniden sipariş önerisi (PRD 3.8). Her stok hareketinden sonra
 * çağrılır (StockMoveService); eşleşen kural yoksa no-op. forecast
 * (qty − reserved_qty) min_qty'nin altına düşerse trigger_type=auto olan
 * kurallarda pending bir öneri oluşturulur/güncellenir; manual kurallarda
 * otomatik oluşturma yapılmaz (yalnızca raporda listelenir).
 */
class ReorderingService
{
    public function evaluate(Product $product, int $locationId): void
    {
        $rule = ReorderingRule::withoutGlobalScopes()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->first();

        if ($rule === null || $rule->trigger_type !== 'auto') {
            return;
        }

        $quant = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->get();

        $forecast = $quant->reduce(fn (string $carry, StockQuant $q) => bcadd($carry, bcsub($q->qty, $q->reserved_qty, 4), 4), '0.0000');

        if (bccomp($forecast, $rule->min_qty, 4) >= 0) {
            return;
        }

        $suggestedQty = bcsub($rule->max_qty, $forecast, 4);

        ReplenishmentSuggestion::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $product->tenant_id, 'reordering_rule_id' => $rule->id, 'status' => 'pending'],
            ['suggested_qty' => $suggestedQty],
        );
    }

    /**
     * $user, öneriyi onaylayan kişidir; olay üzerinden Satınalma
     * modülüne taşınır ve otomatik oluşturulacak draft PO'nun
     * created_by'ı olur (görev ayrılığı bu kullanıcı için de geçerli
     * kalır: kendi onayladığı öneriden doğan PO'yu kendisi confirm edemez).
     */
    public function acknowledge(ReplenishmentSuggestion $suggestion, User $user): void
    {
        $suggestion->update(['status' => 'acknowledged']);

        ReplenishmentAcknowledged::dispatch($suggestion, $user);
    }
}
