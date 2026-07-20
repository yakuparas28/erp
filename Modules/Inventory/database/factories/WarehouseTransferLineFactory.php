<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\WarehouseTransferLine;

/**
 * @extends Factory<WarehouseTransferLine>
 */
class WarehouseTransferLineFactory extends Factory
{
    protected $model = WarehouseTransferLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'warehouse_transfer_id' => WarehouseTransfer::factory(),
            'product_id' => Product::factory(),
            'uom_id' => Uom::factory(),
            'lot_id' => null,
            'qty' => '1.0000',
        ];
    }
}
