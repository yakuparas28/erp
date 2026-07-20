<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\PutawayRule;

/**
 * @extends Factory<PutawayRule>
 */
class PutawayRuleFactory extends Factory
{
    protected $model = PutawayRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => null,
            'product_category_id' => null,
            'source_location_id' => Location::factory(),
            'dest_location_id' => Location::factory(),
            'sequence' => 1,
        ];
    }
}
