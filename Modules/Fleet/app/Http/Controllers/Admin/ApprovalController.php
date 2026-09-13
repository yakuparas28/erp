<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Fleet\Http\Requests\RejectReservationRequest;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * Filo Yöneticisi onay kuyruğu. Tek adım, Gate: approve-vehicle-request.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly FleetNotificationService $notifier) {}

    public function index(): View
    {
        $pending = Reservation::where('onay_durumu', Reservation::ONAY_BEKLEMEDE)
            ->with(['vehicle', 'aktifSofor', 'project', 'ekSoforler'])
            ->orderBy('talep_tarihi')
            ->paginate(20);

        return view('fleet::admin.approvals.index', compact('pending'));
    }

    public function approve(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->onay_durumu === Reservation::ONAY_BEKLEMEDE, 422, 'Bu talep beklemede değil.');

        $reservation->update(['onay_durumu' => Reservation::ONAY_ONAYLANDI]);
        $this->notifier->reservationApproved($reservation);

        return back()->with('success', 'Rezervasyon onaylandı.');
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->onay_durumu === Reservation::ONAY_BEKLEMEDE, 422, 'Bu talep beklemede değil.');

        $reason = (string) $request->validated()['red_aciklamasi'];
        $reservation->update([
            'onay_durumu' => Reservation::ONAY_REDDEDILDI,
            'red_aciklamasi' => $reason,
        ]);
        $this->notifier->reservationRejected($reservation, $reason);

        return back()->with('success', 'Rezervasyon reddedildi.');
    }
}
