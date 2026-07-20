<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;

/**
 * @extends Factory<ReplenishmentSuggestion>
 */
class ReplenishmentSuggestionFactory extends Factory
{
    protected $model = ReplenishmentSuggestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reordering_rule_id' => ReorderingRule::factory(),
            'suggested_qty' => '40.0000',
            'status' => 'pending',
        ];
    }
}
