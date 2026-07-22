<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Journal;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['sale', 'purchase', 'cash', 'bank', 'stock', 'general']),
        ];
    }
}
