<?php

namespace Modules\Fleet\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;

/**
 * BR-RP-01: Rapor sadece teslim edilmiş rezervasyonları sayar. Km/gün
 * ortalamaları teslim_km-alis_km ile teslim/alış tarihi arası gün farkına
 * göre hesaplanır (1 günden kısa süreler 1 gün sayılır).
 */
class VehicleUsageReportService
{
    /**
     * @return Collection<int, array{vehicle: Vehicle, reservation_count: int, toplam_km: int, toplam_gun: int, km_gun_ortalama: float}>
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $tenantId = auth()->user()->tenant_id;

        return Vehicle::where('tenant_id', $tenantId)
            ->orderBy('plaka')
            ->get()
            ->map(function (Vehicle $v) use ($from, $to): array {
                $rezervasyonlar = Reservation::where('arac_id', $v->id)
                    ->where('onay_durumu', Reservation::ONAY_ONAYLANDI)
                    ->whereNotNull('teslim_tarihi')
                    ->whereBetween('teslim_tarihi', [$from, $to])
                    ->get();

                $toplamKm = 0;
                $toplamGun = 0;
                foreach ($rezervasyonlar as $r) {
                    if ($r->alis_km !== null && $r->teslim_km !== null) {
                        $toplamKm += max(0, (int) $r->teslim_km - (int) $r->alis_km);
                    }
                    if ($r->alis_tarihi && $r->teslim_tarihi) {
                        $toplamGun += max(1, (int) ceil($r->alis_tarihi->diffInHours($r->teslim_tarihi) / 24));
                    }
                }

                return [
                    'vehicle' => $v,
                    'reservation_count' => $rezervasyonlar->count(),
                    'toplam_km' => $toplamKm,
                    'toplam_gun' => $toplamGun,
                    'km_gun_ortalama' => $toplamGun > 0 ? round($toplamKm / $toplamGun, 2) : 0.0,
                ];
            });
    }
}
