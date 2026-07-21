<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

class SalesOrderEndToEndTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);
    }

    private function service(): SalesOrderService
    {
        return app(SalesOrderService::class);
    }

    /**
     * Kabul kriteri (PRD 3.11): "Uçtan uca: SO → onay (rezerve) → teslimat
     * (COGS + quant düşer + rezerv kalkar)." Tek metotta tüm zincir kanıtlanır.
     */
    public function test_order_to_cash_flow_reserves_on_confirm_and_delivers_with_cogs_and_releases_reservation(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $unit->id,
            'track_by' => 'none',
            'cost_method' => 'fifo',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $rep = User::factory()->for($this->tenant)->create();
        $rep->assignRole('Sales Representative');

        // Depoya stok girişi: 30 birim @ 6.0000 maliyetle.
        $inboundMove = app(StockMoveService::class)->move(
            tenantId: $this->tenant->id,
            product: $product,
            fromLocationId: null,
            toLocationId: $location->id,
            qty: '30',
            uom: $unit,
            referenceType: 'inventory_adjustment',
            referenceId: 1,
        );
        app(CostingService::class)->recordInbound($product, $inboundMove, '30', '6.0000');

        // 1) SO oluşturulur, satır eklenir.
        $so = $this->service()->create($this->tenant->id, $customer->id, $location->id, $rep);
        $line = $this->service()->addLine($so, $product->id, $unit->id, '10', '9.0000');

        // 2) Teklif gönderilir.
        $this->service()->sendQuotation($so);
        $this->assertSame('quotation_sent', $so->fresh()->status);

        // 3) Onay (yaratıcıdan farklı, yetkili bir kullanıcı tarafından) -> rezervasyon.
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);
        $this->assertSame('confirmed', $so->fresh()->status);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->firstOrFail();
        $this->assertSame('30.0000', $quant->qty);
        $this->assertSame('10.0000', $quant->reserved_qty);

        // 4) Teslimat: çıkış hareketi + COGS oluşur, quant düşer, rezerv kalkar, SO done olur.
        $this->service()->deliver($line, '10');

        $move = StockMove::where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();
        $this->assertSame('-10.0000', $move->qty);

        $layer = StockValuationLayer::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('120.0000', $layer->remaining_value); // (30-10) * 6.0000

        $quant->refresh();
        $this->assertSame('20.0000', $quant->qty);
        $this->assertSame('0.0000', $quant->reserved_qty);

        $this->assertSame('10.0000', $line->fresh()->delivered_qty);
        $this->assertSame('done', $so->fresh()->status);
    }
}
