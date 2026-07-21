<?php

namespace Modules\Sales\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * @extends Factory<SalesOrderLine>
 */
class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => Product::factory(),
            'uom_id' => Uom::factory(),
            'qty' => '10.0000',
            'unit_price' => '5.0000',
            'delivered_qty' => '0.0000',
            'tax_rate_id' => null,
        ];
    }
}
