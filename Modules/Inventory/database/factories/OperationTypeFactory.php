<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\OperationType;

/**
 * @extends Factory<OperationType>
 */
class OperationTypeFactory extends Factory
{
    protected $model = OperationType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('OP-???')),
            'name' => fake()->words(2, true),
            'type' => 'internal',
            'sequence_prefix' => null,
            'active' => true,
        ];
    }
}
