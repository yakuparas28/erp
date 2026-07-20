<?php

namespace Modules\Purchase\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Partner;
use Modules\Purchase\Models\PurchaseOrder;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'partner_id' => Partner::factory(),
            'created_by' => User::factory(),
            'bill_control_policy' => 'received_qty',
            'status' => 'draft',
        ];
    }
}
