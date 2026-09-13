<?php

namespace Modules\Fleet\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\MaintenanceRecord;
use Modules\Fleet\Models\Vehicle;

class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'arac_id' => Vehicle::factory(),
            'giris_yapan_id' => User::factory(),
            'islem_turu' => MaintenanceRecord::ISLEM_BAKIM,
            'yapilan_islemler' => 'Yağ değişimi',
            'kayit_tarihi' => now(),
        ];
    }
}
