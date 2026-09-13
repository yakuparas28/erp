<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\StoreVehicleRequest;
use Modules\Fleet\Http\Requests\UpdateVehicleRequest;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\FleetNotificationService;

class VehicleController extends Controller
{
    public function __construct(private readonly FleetNotificationService $notifier) {}

    public function index(): View
    {
        $vehicles = Vehicle::orderBy('plaka')->paginate(30);

        return view('fleet::admin.vehicles.index', compact('vehicles'));
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::create($request->validated());
        if ($vehicle->durum === Vehicle::DURUM_BLOKELI) {
            $this->notifier->vehicleBlocked($vehicle, auth()->user());
        }

        return back()->with('success', 'Araç kaydedildi.');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $wasBlocked = $vehicle->durum === Vehicle::DURUM_BLOKELI;
        $vehicle->update($request->validated());
        if (! $wasBlocked && $vehicle->fresh()->durum === Vehicle::DURUM_BLOKELI) {
            $this->notifier->vehicleBlocked($vehicle, auth()->user());
        }

        return back()->with('success', 'Araç güncellendi.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        try {
            $vehicle->delete();
        } catch (QueryException) {
            return back()->with('error', 'Araç silinemez: bağlı rezervasyon veya kayıt mevcut.');
        }

        return back()->with('success', 'Araç silindi.');
    }
}
