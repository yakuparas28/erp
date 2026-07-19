<?php

namespace Database\Factories;

use App\Models\LicensePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicensePackage>
 */
class LicensePackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'monthly_price' => fake()->randomFloat(4, 100, 5000),
        ];
    }
}
