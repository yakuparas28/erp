<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\TransferBatch;

/**
 * @extends Factory<TransferBatch>
 */
class TransferBatchFactory extends Factory
{
    protected $model = TransferBatch::class;

    public function definition(): array
    {
        return [
            'name' => 'BATCH-'.fake()->unique()->numerify('####'),
            'status' => 'draft',
            'done_by_user_id' => null,
        ];
    }
}
