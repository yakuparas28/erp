<?php

namespace Modules\Fleet\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;

class VehicleCalendarBlockFactory extends Factory
{
    protected $model = VehicleCalendarBlock::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'block_type' => VehicleCalendarBlock::TYPE_BLOKE,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ];
    }
}
