<?php

namespace Modules\Inventory\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Scrap;
use Modules\Inventory\Models\Uom;

/**
 * Odoo `stock.scrap` denkliği: verilen kaynak lokasyondan ürünü tenant'ın
 * `Scrap` sanal lokasyonuna taşır ve `scraps` tablosuna insan-okunur bir
 * kayıt bırakır. Fire kaydı StockMove ile de tutulur (immutable ledger).
 */
class ScrapService
{
    public function __construct(private readonly StockMoveService $stockMoves) {}

    public function scrap(
        int $tenantId,
        Product $product,
        int $sourceLocationId,
        Uom $uom,
        string $qty,
        ?int $lotId,
        ?string $reason,
        User $doneBy,
    ): Scrap {
        abort_if(bccomp($qty, '0', 4) <= 0, 422, __('Scrap quantity must be positive.'));

        $scrapLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'scrap')
            ->first();

        abort_if($scrapLocation === null, 422, __('Scrap location is not configured for this tenant.'));

        return DB::transaction(function () use ($tenantId, $product, $sourceLocationId, $scrapLocation, $uom, $qty, $lotId, $reason, $doneBy): Scrap {
            // İç lokasyondan çıkar (current_stock düşer).
            $this->stockMoves->move(
                $tenantId,
                $product,
                $sourceLocationId,
                null,
                '-'.$qty,
                $uom,
                'scrap',
                0,
                $lotId,
            );

            $scrap = new Scrap([
                'product_id' => $product->id,
                'source_location_id' => $sourceLocationId,
                'scrap_location_id' => $scrapLocation->id,
                'lot_id' => $lotId,
                'uom_id' => $uom->id,
                'qty' => $qty,
                'reason' => $reason,
                'done_by_user_id' => $doneBy->id,
                'scrapped_at' => now(),
            ]);
            $scrap->tenant_id = $tenantId;
            $scrap->save();

            return $scrap;
        });
    }
}
