<?php

namespace Modules\Inventory\Services;

use App\Models\Tenant;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;

/**
 * Yeni tenant için envanter varsayılanları: ana depo + kök lokasyon +
 * sanal lokasyonlar (PRD 3.4) + referans birim. Idempotent; tenant_id
 * explicit taşınır (auth bağlamına güvenilmez — PRD 4.2).
 */
class InventoryDefaultsService
{
    public function provision(Tenant $tenant): void
    {
        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ANA'],
            ['name' => 'Ana Depo'],
        );

        Location::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id, 'name' => 'Stok'],
            ['type' => 'internal'],
        );

        $virtualLocations = [
            'Müşteriler' => 'customer',
            'Tedarikçiler' => 'supplier',
            'Sayım Farkı / Zayiat' => 'inventory_loss',
            'Transit' => 'transit',
        ];

        foreach ($virtualLocations as $name => $type) {
            Location::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $type],
                ['name' => $name, 'warehouse_id' => null],
            );
        }

        $category = UomCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Birim'],
        );

        Uom::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true],
            ['name' => 'Adet', 'factor' => '1.000000'],
        );
    }
}
