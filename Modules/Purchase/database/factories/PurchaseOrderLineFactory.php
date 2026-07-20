<?php

namespace Modules\Purchase\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'uom_id' => Uom::factory(),
            'qty' => '10.0000',
            'unit_price' => '5.0000',
            'tax_rate_id' => null,
        ];
    }
}
