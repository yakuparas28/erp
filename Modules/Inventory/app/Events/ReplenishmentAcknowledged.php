<?php

namespace Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\ReplenishmentSuggestion;

/**
 * Bir yeniden sipariş önerisi onaylandığında fırlatılır (PRD 3.8).
 * Faz 7'de Satınalma modülü bu olayı dinleyip varsayılan tedarikçiye
 * otomatik bir draft purchase_orders kaydı üretecek — bu fazda listener yok.
 */
class ReplenishmentAcknowledged
{
    use Dispatchable;

    public function __construct(public ReplenishmentSuggestion $suggestion) {}
}
