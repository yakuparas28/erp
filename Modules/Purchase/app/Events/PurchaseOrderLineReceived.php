<?php

namespace Modules\Purchase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\StockMove;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Bir satınalma sipariş satırı fiilen teslim alındığında fırlatılır
 * (PRD 3.10/3.12). Accounting modülü bu olayı dinleyip mal değeri kadar
 * yevmiye kaydı üretir (bkz. Faz 9 listener).
 */
class PurchaseOrderLineReceived
{
    use Dispatchable;

    public function __construct(
        public PurchaseOrderLine $line,
        public StockMove $move,
    ) {}
}
