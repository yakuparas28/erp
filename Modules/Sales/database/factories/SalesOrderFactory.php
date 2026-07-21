<?php

namespace Modules\Sales\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Sales\Models\SalesOrder;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'partner_id' => Partner::factory(),
            'location_id' => Location::factory(),
            'created_by' => User::factory(),
            'status' => 'draft',
        ];
    }
}
