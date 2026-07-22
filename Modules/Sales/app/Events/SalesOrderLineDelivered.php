<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\StockMove;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Bir satış sipariş satırı fiilen teslim edildiğinde fırlatılır
 * (PRD 3.11/3.12). Accounting modülü bu olayı dinleyip (yalnızca
 * anglo_saxon modda) COGS tutarı kadar yevmiye kaydı üretir (bkz.
 * Faz 9 listener).
 */
class SalesOrderLineDelivered
{
    use Dispatchable;

    public function __construct(
        public SalesOrderLine $line,
        public StockMove $move,
        public string $cogsAmount,
    ) {}
}
