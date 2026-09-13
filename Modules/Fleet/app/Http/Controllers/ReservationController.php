<?php

namespace Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\ConfirmPickupRequest;
use Modules\Fleet\Http\Requests\StoreReservationRequest;
use Modules\Fleet\Http\Requests\SubmitDeliveryRequest;
use Modules\Fleet\Models\Project;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleUsageRule;
use Modules\Fleet\Services\AvailableVehicleFinder;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * Personel tarafındaki rezervasyon akışı: taleplerim listesi, iki-adımlı
 * oluşturma sihirbazı (arama → araç seçimi), alış teyidi ve teslim başvurusu.
 * Onay ve teslim onayı admin akışında.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly AvailableVehicleFinder $finder,
        private readonly FleetNotificationService $notifier,
    ) {}

    public function index(): View
    {
        $reservations = Reservation::where('aktif_sofor_id', auth()->id())
            ->with(['vehicle', 'project', 'ekSoforler'])
            ->orderByDesc('talep_tarihi')
            ->orderByDesc('id')
            ->paginate(20);

        return view('fleet::rezervasyon.index', compact('reservations'));
    }

    public function create(Request $request): View
    {
        $pickupInput = (string) $request->input('planlanan_alis_at', '');
        $returnInput = (string) $request->input('planlanan_teslim_at', '');

        $availableVehicles = collect();
        $searchError = null;
        $pickup = null;
        $return = null;

        if ($pickupInput !== '' && $returnInput !== '') {
            try {
                $pickup = CarbonImmutable::parse($pickupInput);
                $return = CarbonImmutable::parse($returnInput);
                if ($pickup->lte(now())) {
                    $searchError = 'Alış zamanı gelecekte olmalı.';
                } elseif ($return->lte($pickup)) {
                    $searchError = 'Teslim zamanı alış zamanından sonra olmalı.';
                } else {
                    $availableVehicles = $this->finder->available($pickup, $return);
                }
            } catch (\Throwable $e) {
                $searchError = 'Tarih formatı hatalı.';
            }
        }

        $projects = Project::where('aktif', true)->orderBy('ad')->get();
        $candidateDrivers = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get();
        $usageRule = VehicleUsageRule::forTenant(auth()->user()->tenant_id);

        return view('fleet::rezervasyon.create', compact(
            'availableVehicles', 'projects', 'candidateDrivers', 'usageRule',
            'pickup', 'return', 'searchError'
        ));
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $reservation = DB::transaction(function () use ($data) {
            $r = Reservation::create([
                'arac_id' => (int) $data['arac_id'],
                'aktif_sofor_id' => auth()->id(),
                'proje_id' => $data['proje_id'] ?? null,
                'planlanan_alis_at' => $data['planlanan_alis_at'],
                'planlanan_teslim_at' => $data['planlanan_teslim_at'],
                'talep_tarihi' => now(),
                'onay_durumu' => Reservation::ONAY_BEKLEMEDE,
                'teslim_beyani' => false,
            ]);

            if (! empty($data['ek_soforler'])) {
                $r->ekSoforler()->sync($data['ek_soforler']);
            }

            return $r;
        });

        $this->notifier->reservationCreated($reservation);

        return redirect()->route('app.fleet.reservations.index')
            ->with('success', 'Rezervasyon talebiniz Filo Yöneticisi onayına gönderildi.');
    }

    public function showPickup(Reservation $reservation): View
    {
        $this->assertOwnedAndPickable($reservation);
        $reservation->load(['vehicle', 'project']);

        return view('fleet::rezervasyon.alis', compact('reservation'));
    }

    public function confirmPickup(ConfirmPickupRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->assertOwnedAndPickable($reservation);

        $okunanKm = (int) $request->validated()['okunan_km'];
        $sistemKm = (int) ($reservation->vehicle->guncel_km ?? 0);
        $sapma = $okunanKm - $sistemKm;

        DB::transaction(function () use ($reservation, $okunanKm, $sapma) {
            $reservation->update([
                'alis_km' => $okunanKm,
                'alis_km_sapma' => $sapma === 0 ? null : $sapma,
                'alis_tarihi' => now(),
            ]);
            $reservation->vehicle()->update([
                'guncel_km' => $okunanKm,
                'durum' => Vehicle::DURUM_AKTIF,
            ]);
        });

        $reservation->refresh();
        $this->notifier->pickupConfirmed($reservation);
        if ($sapma !== 0) {
            $this->notifier->mileageDeviation($reservation, $sistemKm, $okunanKm, $sapma);
        }

        return redirect()->route('app.fleet.reservations.index')
            ->with('success', 'Alış tamamlandı.');
    }

    public function showDelivery(Reservation $reservation): View
    {
        $this->assertOwnedAndDeliverable($reservation);
        $reservation->load(['vehicle', 'project']);

        return view('fleet::rezervasyon.teslim', compact('reservation'));
    }

    public function submitDelivery(SubmitDeliveryRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->assertOwnedAndDeliverable($reservation);

        $photos = [];
        foreach (['on', 'arka', 'sag', 'sol', 'km'] as $slot) {
            $path = $request->file("teslim_foto_{$slot}")
                ->store("reservations/{$reservation->id}", 'public');
            $photos[$slot] = $path;
        }

        $reservation->update([
            'teslim_km' => (int) $request->input('teslim_km'),
            'teslim_beyani' => (bool) $request->input('teslim_beyani'),
            'teslim_ariza_aciklamasi' => $request->input('teslim_ariza_aciklamasi'),
            'teslim_fotograflari' => $photos,
            'teslim_basvurusu_tarihi' => now(),
        ]);

        return redirect()->route('app.fleet.reservations.index')
            ->with('success', 'Teslim başvurunuz Filo Yöneticisi onayına gönderildi.');
    }

    private function assertOwnedAndPickable(Reservation $r): void
    {
        abort_unless(
            $r->aktif_sofor_id === auth()->id()
            && $r->onay_durumu === Reservation::ONAY_ONAYLANDI
            && $r->alis_tarihi === null,
            403,
            'Bu rezervasyon için alış yapılamaz.',
        );
    }

    private function assertOwnedAndDeliverable(Reservation $r): void
    {
        $isOwner = $r->aktif_sofor_id === auth()->id()
            || $r->ekSoforler->contains(auth()->id());
        abort_unless(
            $isOwner && $r->alis_tarihi !== null && $r->teslim_basvurusu_tarihi === null,
            403,
            'Bu rezervasyon için teslim başvurusu yapılamaz.',
        );
    }
}
