<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Route;
use Modules\Inventory\Models\RouteRule;

/**
 * @extends Factory<RouteRule>
 */
class RouteRuleFactory extends Factory
{
    protected $model = RouteRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'route_id' => Route::factory(),
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'action' => 'push',
            'sequence' => 1,
        ];
    }
}
