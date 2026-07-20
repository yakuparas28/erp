<?php

namespace Tests\Feature\Inventory;

use App\Models\Tenant;
use InvalidArgumentException;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Inventory\Services\UomConversionService;
use Tests\TenantTestCase;

class UomAndDefaultsTest extends TenantTestCase
{
    public function test_derived_uom_converts_to_reference(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $box = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Koli (12li)',
            'factor' => '12.000000',
            'is_reference' => false,
        ]);

        $this->assertSame('36.0000', app(UomConversionService::class)->toReference($box, '3'));
    }

    public function test_reference_uom_passes_through(): void
    {
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertSame('5.0000', app(UomConversionService::class)->toReference($uom, '5'));
    }

    public function test_negative_or_zero_factor_rejected(): void
    {
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'factor' => '0.000000', 'is_reference' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(UomConversionService::class)->toReference($uom, '1');
    }

    public function test_inventory_defaults_are_provisioned_for_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        app(InventoryDefaultsService::class)->provision($tenant);

        $this->assertDatabaseHas('warehouses', ['tenant_id' => $tenant->id]);

        foreach (['internal', 'customer', 'supplier', 'inventory_loss', 'transit'] as $type) {
            $this->assertTrue(
                Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', $type)->exists(),
                "{$type} lokasyonu eksik",
            );
        }

        $this->assertTrue(
            Uom::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_reference', true)->exists(),
        );

        // idempotent
        app(InventoryDefaultsService::class)->provision($tenant);
        $this->assertSame(1, Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
