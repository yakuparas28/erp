<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * Anahtar teslim kuyruğu: personel teslim başvurusu göndermiş, henüz teslim
 * onaylanmamış rezervasyonlar. BR-AZ-14: FY isterse aracı zorla garaja çeker.
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly FleetNotificationService $notifier) {}

    public function index(): View
    {
        $pending = Reservation::whereNotNull('teslim_basvurusu_tarihi')
            ->whereNull('teslim_tarihi')
            ->with(['vehicle', 'aktifSofor', 'project'])
            ->orderBy('teslim_basvurusu_tarihi')
            ->paginate(20);

        return view('fleet::admin.deliveries.index', compact('pending'));
    }

    public function confirm(Reservation $reservation): RedirectResponse
    {
        abort_unless(
            $reservation->teslim_basvurusu_tarihi !== null && $reservation->teslim_tarihi === null,
            422, 'Bu rezervasyon için teslim onayı verilemez.',
        );

        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'teslim_tarihi' => now(),
                'closure_reason' => Reservation::CLOSURE_NORMAL,
            ]);
            $reservation->vehicle()->update([
                'guncel_km' => $reservation->teslim_km,
                'durum' => Vehicle::DURUM_GARAJDA,
            ]);
        });

        $this->notifier->deliveryCompleted($reservation->fresh(['vehicle', 'aktifSofor']));

        return back()->with('success', 'Teslim tamamlandı.');
    }

    public function forceGarage(Reservation $reservation): RedirectResponse
    {
        abort_unless(
            $reservation->onay_durumu === Reservation::ONAY_ONAYLANDI && $reservation->teslim_tarihi === null,
            422, 'Bu rezervasyon zorla kapatılamaz.',
        );

        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'teslim_tarihi' => now(),
                'closure_reason' => Reservation::CLOSURE_FILO_FORCED,
            ]);
            $reservation->vehicle()->update(['durum' => Vehicle::DURUM_GARAJDA]);
        });

        return back()->with('success', 'Araç garaja çekildi.');
    }
}
