<?php

namespace Modules\Inventory\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\ReplenishmentSuggestion;

/**
 * Bir yeniden sipariş önerisi onaylandığında fırlatılır (PRD 3.8).
 * Satınalma modülü bu olayı dinleyip ürünün varsayılan tedarikçisine
 * otomatik bir draft purchase_orders kaydı üretir (bkz. Faz 7 listener).
 */
class ReplenishmentAcknowledged
{
    use Dispatchable;

    public function __construct(
        public ReplenishmentSuggestion $suggestion,
        public User $acknowledgedBy,
    ) {}
}
