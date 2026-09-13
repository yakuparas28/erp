<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fleet\Database\Factories\VehicleFactory;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToTenant, HasFactory;

    public const DURUM_GARAJDA = 'Garajda';

    public const DURUM_AKTIF = 'Aktif_Kullanimda';

    public const DURUM_BLOKELI = 'Blokeli_Bakimda';

    public const MTV_ODENDI = 'Odendi';

    public const MTV_ODENMEDI = 'Odenmedi';

    public const MTV_KISMI = 'Kismi_Odendi';

    protected $fillable = [
        'tenant_id', 'plaka', 'marka_model', 'yil', 'sasi_no', 'motor_no', 'guncel_km',
        'bakim_tarihi', 'muayene_tarihi', 'bakim_uyari_son_gonderim', 'muayene_uyari_son_gonderim',
        'mtv_odeme_tarihi', 'mtv_odeme_durumu', 'mtv_odeme_notu', 'mtv_uyari_son_gonderim', 'durum',
    ];

    protected function casts(): array
    {
        return [
            'yil' => 'integer',
            'guncel_km' => 'integer',
            'bakim_tarihi' => 'date',
            'muayene_tarihi' => 'date',
            'bakim_uyari_son_gonderim' => 'date',
            'muayene_uyari_son_gonderim' => 'date',
            'mtv_odeme_tarihi' => 'date',
            'mtv_uyari_son_gonderim' => 'date',
        ];
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'arac_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'arac_id');
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(VehicleCalendarBlock::class);
    }
}
