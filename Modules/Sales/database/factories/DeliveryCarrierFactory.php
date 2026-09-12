<?php

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\DeliveryCarrier;

/**
 * @extends Factory<DeliveryCarrier>
 */
class DeliveryCarrierFactory extends Factory
{
    protected $model = DeliveryCarrier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('CAR???')),
            'tracking_url_template' => 'https://track.example.com/{tracking_number}',
            'active' => true,
        ];
    }
}
