<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;

class FleetDashboardController extends Controller
{
    public function index(): View
    {
        $today = today();
        $vehicles = Vehicle::orderBy('plaka')->get();

        $byDurum = $vehicles->groupBy('durum');
        $setting = FleetTaskSetting::forTenant(auth()->user()->tenant_id);
        $window = $today->copy()->addDays($setting->critical_window_days);

        $criticalWindow = $vehicles->filter(fn (Vehicle $v) => (
            ($v->bakim_tarihi && $v->bakim_tarihi->between($today, $window))
            || ($v->muayene_tarihi && $v->muayene_tarihi->between($today, $window))
        ))->values();

        $activeRides = Reservation::where('onay_durumu', Reservation::ONAY_ONAYLANDI)
            ->whereNotNull('alis_tarihi')
            ->whereNull('teslim_tarihi')
            ->with(['vehicle', 'aktifSofor'])
            ->orderBy('alis_tarihi')
            ->limit(10)
            ->get();

        $upcomingMaintenance = $vehicles
            ->map(function (Vehicle $v) use ($today) {
                $items = [];
                foreach ([['tur' => 'Bakım', 'date' => $v->bakim_tarihi],
                    ['tur' => 'Muayene', 'date' => $v->muayene_tarihi],
                    ['tur' => 'MTV', 'date' => $v->mtv_odeme_tarihi]] as $slot) {
                    if ($slot['date'] === null) {
                        continue;
                    }
                    $days = $today->diffInDays($slot['date'], false);
                    if ($days >= 0 && $days <= 90) {
                        $items[] = ['vehicle' => $v, 'tur' => $slot['tur'], 'date' => $slot['date'], 'days' => (int) $days];
                    }
                }

                return $items;
            })
            ->flatten(1)
            ->sortBy('days')
            ->values();

        $mtvOverdue = $vehicles->filter(fn (Vehicle $v) => (
            $v->mtv_odeme_tarihi && $v->mtv_odeme_tarihi->lt($today) && $v->mtv_odeme_durumu === Vehicle::MTV_ODENMEDI
        ))->values();

        $kpis = [
            'avg_km' => (int) round((float) $vehicles->avg('guncel_km')),
            'pending_approvals' => Reservation::where('onay_durumu', Reservation::ONAY_BEKLEMEDE)->count(),
            'active_rides' => $activeRides->count(),
            'critical_count' => $criticalWindow->count(),
            'mtv_overdue' => $mtvOverdue->count(),
        ];

        return view('fleet::admin.fleet-dashboard.index', compact(
            'vehicles', 'byDurum', 'criticalWindow', 'activeRides', 'upcomingMaintenance', 'mtvOverdue', 'kpis'
        ));
    }
}
