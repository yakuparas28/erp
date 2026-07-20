<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Models\SyncBatch;

/**
 * @extends Factory<SyncBatch>
 */
class SyncBatchFactory extends Factory
{
    protected $model = SyncBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'batch_uuid' => Str::uuid()->toString(),
        ];
    }
}
