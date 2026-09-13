<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\StoreMaintenanceRecordRequest;
use Modules\Fleet\Models\MaintenanceRecord;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\FleetNotificationService;

class MaintenanceRecordController extends Controller
{
    public function __construct(private readonly FleetNotificationService $notifier) {}

    public function index(): View
    {
        $records = MaintenanceRecord::with(['vehicle', 'girisYapan'])
            ->orderByDesc('kayit_tarihi')
            ->paginate(30);

        return view('fleet::admin.maintenance.index', compact('records'));
    }

    public function store(StoreMaintenanceRecordRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $record = DB::transaction(function () use ($request, $vehicle) {
            $r = MaintenanceRecord::create([
                'arac_id' => $vehicle->id,
                'giris_yapan_id' => auth()->id(),
                'kayit_tarihi' => now(),
            ] + $request->validated());

            $updates = [];
            if ($r->yeni_bakim_tarihi) {
                $updates['bakim_tarihi'] = $r->yeni_bakim_tarihi;
            }
            if ($r->yeni_muayene_tarihi) {
                $updates['muayene_tarihi'] = $r->yeni_muayene_tarihi;
            }
            if ($vehicle->durum === Vehicle::DURUM_BLOKELI) {
                $updates['durum'] = Vehicle::DURUM_GARAJDA;
            }
            if ($updates !== []) {
                $vehicle->update($updates);
            }

            return $r;
        });

        $this->notifier->vehicleUnblocked($record);

        return back()->with('success', 'Bakım kaydı eklendi.');
    }
}
