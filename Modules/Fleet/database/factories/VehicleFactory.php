<?php

namespace Modules\Fleet\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\Vehicle;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plaka' => strtoupper(fake()->bothify('##???###')),
            'marka_model' => fake()->randomElement(['Ford Transit', 'Renault Kangoo', 'Fiat Doblo', 'Toyota Corolla']),
            'yil' => fake()->numberBetween(2015, 2025),
            'guncel_km' => fake()->numberBetween(10000, 200000),
            'durum' => Vehicle::DURUM_GARAJDA,
            'mtv_odeme_durumu' => Vehicle::MTV_ODENMEDI,
        ];
    }
}
