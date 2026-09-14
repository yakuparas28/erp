<?php

namespace Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\StoreReservationRequest;
use Modules\Fleet\Models\Project;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * Filo takvimi: satırlar araç, sütunlar aydaki günler. Her hücre için
 * öncelik sırası bloke > aktif > bekleyen > boş.
 */
class FleetCalendarController extends Controller
{
    public function __construct(private readonly FleetNotificationService $notifier) {}

    public function index(Request $request): View
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        $year = max(2020, min(2099, $year));
        $month = max(1, min(12, $month));

        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();

        $tenantId = auth()->user()->tenant_id;

        $vehicles = Vehicle::where('tenant_id', $tenantId)
            ->orderBy('plaka')
            ->get();

        $reservations = Reservation::where('tenant_id', $tenantId)
            ->whereIn('onay_durumu', [Reservation::ONAY_BEKLEMEDE, Reservation::ONAY_ONAYLANDI])
            ->where('planlanan_alis_at', '<=', $end)
            ->where('planlanan_teslim_at', '>=', $start)
            ->with(['vehicle', 'aktifSofor'])
            ->get()
            ->groupBy('arac_id');

        $blocks = VehicleCalendarBlock::where('tenant_id', $tenantId)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get()
            ->groupBy('vehicle_id');

        $canReserve = auth()->user()->can('reserve vehicle');
        $projects = $canReserve ? Project::where('aktif', true)->orderBy('ad')->get() : collect();
        $drivers = $canReserve
            ? User::where('tenant_id', $tenantId)->where('id', '!=', auth()->id())->orderBy('name')->get()
            : collect();

        return view('fleet::filo-takvimi.index', compact(
            'vehicles', 'reservations', 'blocks', 'start', 'end',
            'year', 'month', 'canReserve', 'projects', 'drivers'
        ));
    }

    /**
     * Takvim üzerinden iki tıklı rezervasyon. Personel boş yeşil bir hücreye
     * tıklar (alış), sonra aynı araç satırında ileri bir tarihe tıklar (teslim);
     * modal açılır. Aynı store logic'i, sadece redirect calendar'a döner.
     */
    public function reserve(StoreReservationRequest $request): RedirectResponse
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

        return redirect()
            ->route('app.fleet.calendar.index', ['year' => (int) $request->integer('__year', now()->year), 'month' => (int) $request->integer('__month', now()->month)])
            ->with('success', 'Rezervasyon talebiniz Filo Yöneticisi onayına gönderildi.');
    }
}
