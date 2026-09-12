<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\StorageCategory;

/**
 * @extends Factory<StorageCategory>
 */
class StorageCategoryFactory extends Factory
{
    protected $model = StorageCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'max_weight' => '1000.0000',
            'allow_new_product' => 'mixed',
        ];
    }
}
