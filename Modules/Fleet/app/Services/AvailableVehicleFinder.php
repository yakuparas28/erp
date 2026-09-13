<?php

namespace Modules\Fleet\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;

/**
 * Belirli bir zaman aralığı için müsait araçları döner. Kurallar:
 * - BR-AZ-01: `vehicle_calendar_blocks` overlap yok.
 * - BR-AZ-12: Aynı aralıkta pending/approved + teslim_tarihi=null rezervasyon yok.
 * - BR-AZ-02: Teslim zamanı sonraki bakım/muayene bloğunun başlangıcından
 *   en az 1 iş günü önce olmalı (aksi hâlde araç önerilmez).
 */
class AvailableVehicleFinder
{
    /**
     * @return Collection<int, Vehicle>
     */
    public function available(CarbonInterface $pickupAt, CarbonInterface $returnAt, ?int $excludeReservationId = null): Collection
    {
        $tenantId = auth()->user()->tenant_id;

        return Vehicle::where('tenant_id', $tenantId)
            ->where('durum', Vehicle::DURUM_GARAJDA)
            ->orderBy('plaka')
            ->get()
            ->reject(function (Vehicle $v) use ($pickupAt, $returnAt, $excludeReservationId): bool {
                return $this->hasBlockOverlap($v, $pickupAt, $returnAt)
                    || $this->hasReservationOverlap($v, $pickupAt, $returnAt, $excludeReservationId)
                    || ! $this->hasOneBusinessDayBufferBeforeBlock($v, $returnAt);
            })
            ->values();
    }

    private function hasBlockOverlap(Vehicle $vehicle, CarbonInterface $pickup, CarbonInterface $return): bool
    {
        return VehicleCalendarBlock::where('vehicle_id', $vehicle->id)
            ->where('start_date', '<=', $return->toDateString())
            ->where('end_date', '>=', $pickup->toDateString())
            ->exists();
    }

    private function hasReservationOverlap(Vehicle $vehicle, CarbonInterface $pickup, CarbonInterface $return, ?int $excludeId): bool
    {
        return Reservation::where('arac_id', $vehicle->id)
            ->whereIn('onay_durumu', [Reservation::ONAY_BEKLEMEDE, Reservation::ONAY_ONAYLANDI])
            ->whereNull('teslim_tarihi')
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('planlanan_alis_at', '<', $return)
            ->where('planlanan_teslim_at', '>', $pickup)
            ->exists();
    }

    private function hasOneBusinessDayBufferBeforeBlock(Vehicle $vehicle, CarbonInterface $returnAt): bool
    {
        $next = VehicleCalendarBlock::where('vehicle_id', $vehicle->id)
            ->where('start_date', '>=', $returnAt->toDateString())
            ->orderBy('start_date')
            ->first();

        if ($next === null) {
            return true;
        }

        return $returnAt->copy()->addWeekday()->lte($next->start_date);
    }
}
