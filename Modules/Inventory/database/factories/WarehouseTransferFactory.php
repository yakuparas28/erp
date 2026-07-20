<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\WarehouseTransfer;

/**
 * @extends Factory<WarehouseTransfer>
 */
class WarehouseTransferFactory extends Factory
{
    protected $model = WarehouseTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'created_by' => User::factory(),
            'status' => 'draft',
        ];
    }
}
