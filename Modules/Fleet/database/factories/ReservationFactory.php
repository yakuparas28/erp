<?php

namespace Modules\Fleet\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'arac_id' => Vehicle::factory(),
            'aktif_sofor_id' => User::factory(),
            'planlanan_alis_at' => now()->addDays(2),
            'planlanan_teslim_at' => now()->addDays(4),
            'talep_tarihi' => now(),
            'onay_durumu' => Reservation::ONAY_BEKLEMEDE,
            'teslim_beyani' => false,
        ];
    }
}
