<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Scrap;

/**
 * @extends Factory<Scrap>
 */
class ScrapFactory extends Factory
{
    protected $model = Scrap::class;

    public function definition(): array
    {
        return [
            'qty' => '1.0000',
            'reason' => null,
            'scrapped_at' => now(),
        ];
    }
}
