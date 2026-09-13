<?php

namespace Modules\Fleet\Models;

use App\Concerns\HasApproval;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Fleet\Database\Factories\ReservationFactory;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use BelongsToTenant, HasApproval, HasFactory;

    public const ONAY_BEKLEMEDE = 'Beklemede';

    public const ONAY_ONAYLANDI = 'Onaylandi';

    public const ONAY_REDDEDILDI = 'Reddedildi';

    public const CLOSURE_NORMAL = 'normal_teslim';

    public const CLOSURE_FILO_FORCED = 'filo_garaja_cekti';

    protected $fillable = [
        'tenant_id', 'arac_id', 'aktif_sofor_id', 'proje_id',
        'planlanan_alis_at', 'planlanan_teslim_at',
        'alis_km', 'alis_km_sapma', 'teslim_km',
        'talep_tarihi', 'alis_tarihi', 'teslim_basvurusu_tarihi', 'teslim_tarihi',
        'onay_durumu', 'red_aciklamasi', 'teslim_beyani',
        'teslim_fotograflari', 'teslim_ariza_aciklamasi', 'closure_reason',
    ];

    protected function casts(): array
    {
        return [
            'alis_km' => 'integer',
            'alis_km_sapma' => 'integer',
            'teslim_km' => 'integer',
            'planlanan_alis_at' => 'datetime',
            'planlanan_teslim_at' => 'datetime',
            'talep_tarihi' => 'datetime',
            'alis_tarihi' => 'datetime',
            'teslim_basvurusu_tarihi' => 'datetime',
            'teslim_tarihi' => 'datetime',
            'teslim_beyani' => 'boolean',
            'teslim_fotograflari' => 'array',
        ];
    }

    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'arac_id');
    }

    public function aktifSofor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktif_sofor_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proje_id');
    }

    public function ekSoforler(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reservation_drivers', 'reservation_id', 'personel_id');
    }
}
