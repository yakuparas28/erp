<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Inventory\Services\ScrapService;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class ScrapTest extends TenantTestCase
{
    private Location $stockLocation;

    private Product $product;

    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        app(InventoryDefaultsService::class)->provision($this->tenant);

        $this->stockLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();

        $this->uom = Uom::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
        ]);

        app(StockMoveService::class)->move(
            $this->tenant->id,
            $this->product,
            null,
            $this->stockLocation->id,
            '10',
            $this->uom,
            'seed',
            0,
        );

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('perform scrap operations');
    }

    public function test_scrap_moves_product_to_scrap_location_and_records_a_log(): void
    {
        app(ScrapService::class)->scrap(
            $this->tenant->id,
            $this->product,
            $this->stockLocation->id,
            $this->uom,
            '3',
            null,
            'Damaged',
            $this->tenantAdmin,
        );

        $scrapLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'scrap')->firstOrFail();

        $this->assertDatabaseHas('scraps', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'source_location_id' => $this->stockLocation->id,
            'scrap_location_id' => $scrapLocation->id,
            'qty' => '3.0000',
            'reason' => 'Damaged',
        ]);

        $this->assertSame(1, StockMove::where('reference_type', 'scrap')->count());
        $this->assertSame('7.0000', $this->product->fresh()->current_stock);
    }

    public function test_scrap_quantity_must_be_positive(): void
    {
        $this->expectException(HttpException::class);

        app(ScrapService::class)->scrap(
            $this->tenant->id,
            $this->product,
            $this->stockLocation->id,
            $this->uom,
            '0',
            null,
            null,
            $this->tenantAdmin,
        );
    }

    public function test_scrap_endpoint_requires_permission(): void
    {
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/scraps')
            ->assertForbidden();
    }

    public function test_scrap_endpoint_creates_a_scrap_record(): void
    {
        $response = $this->actingAs($this->tenantAdmin)->post('/app/inventory/scraps', [
            'product_id' => $this->product->id,
            'source_location_id' => $this->stockLocation->id,
            'uom_id' => $this->uom->id,
            'qty' => '2',
            'reason' => 'Broken',
        ]);

        $response->assertRedirect(route('app.inventory.scraps.index'));

        $this->assertDatabaseHas('scraps', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'qty' => '2.0000',
            'reason' => 'Broken',
        ]);
    }
}
