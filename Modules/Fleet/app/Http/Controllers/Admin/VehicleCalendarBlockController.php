<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\StoreCalendarBlockRequest;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;

class VehicleCalendarBlockController extends Controller
{
    public function index(): View
    {
        $today = today()->toDateString();
        $vehicles = Vehicle::with(['calendarBlocks' => function ($q) use ($today) {
            $q->where('end_date', '>=', $today)->orderBy('start_date')->limit(3);
        }])->orderBy('plaka')->get();

        return view('fleet::admin.vehicle-calendar.index', compact('vehicles'));
    }

    public function show(Vehicle $vehicle): View
    {
        $today = today()->toDateString();

        $upcomingBlocks = VehicleCalendarBlock::where('vehicle_id', $vehicle->id)
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get();

        $plannedReservations = Reservation::where('arac_id', $vehicle->id)
            ->whereIn('onay_durumu', [Reservation::ONAY_BEKLEMEDE, Reservation::ONAY_ONAYLANDI])
            ->whereNull('teslim_tarihi')
            ->with(['aktifSofor', 'project'])
            ->orderBy('planlanan_alis_at')
            ->get();

        $usageHistory = Reservation::where('arac_id', $vehicle->id)
            ->whereNotNull('teslim_tarihi')
            ->with(['aktifSofor', 'project'])
            ->orderByDesc('teslim_tarihi')
            ->limit(50)
            ->get();

        return view('fleet::admin.vehicle-calendar.show', compact(
            'vehicle', 'upcomingBlocks', 'plannedReservations', 'usageHistory'
        ));
    }

    public function store(StoreCalendarBlockRequest $request, Vehicle $vehicle): RedirectResponse
    {
        VehicleCalendarBlock::create([
            'vehicle_id' => $vehicle->id,
            'created_by_id' => auth()->id(),
        ] + $request->validated());

        return back()->with('success', 'Takvim bloğu kaydedildi.');
    }

    public function destroy(VehicleCalendarBlock $block): RedirectResponse
    {
        $vehicleId = $block->vehicle_id;
        $block->delete();

        return redirect()
            ->route('app.fleet.vehicle-calendar.show', $vehicleId)
            ->with('success', 'Blok silindi.');
    }
}
