<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fleet\Database\Factories\MaintenanceRecordFactory;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use BelongsToTenant, HasFactory;

    public const ISLEM_BAKIM = 'Bakim';

    public const ISLEM_MUAYENE = 'Muayene';

    protected $fillable = [
        'tenant_id', 'arac_id', 'giris_yapan_id', 'islem_turu',
        'yapilan_islemler', 'degisen_parcalar',
        'yeni_bakim_tarihi', 'yeni_muayene_tarihi', 'kayit_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'yeni_bakim_tarihi' => 'date',
            'yeni_muayene_tarihi' => 'date',
            'kayit_tarihi' => 'datetime',
        ];
    }

    protected static function newFactory(): MaintenanceRecordFactory
    {
        return MaintenanceRecordFactory::new();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'arac_id');
    }

    public function girisYapan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giris_yapan_id');
    }
}
