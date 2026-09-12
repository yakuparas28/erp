<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\PackageType;

/**
 * @extends Factory<PackageType>
 */
class PackageTypeFactory extends Factory
{
    protected $model = PackageType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'barcode' => null,
            'height' => '10.0000',
            'width' => '10.0000',
            'packaging_length' => '10.0000',
            'max_weight' => '20.0000',
        ];
    }
}
