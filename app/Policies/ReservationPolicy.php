<?php

namespace App\Policies;

use App\Models\User;
use Modules\Fleet\Models\Reservation;

/**
 * Fleet rezervasyon yetkileri: sahiplik + statü + FY rolü kombinasyonu.
 * Alış/teslim akışında `aktif_sofor_id` sahiplik kontrolü tekrar
 * ediliyordu — burada tek yerde.
 */
class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->can('approve-vehicle-request') || $user->can('confirm vehicle delivery')) {
            return true;
        }

        return $this->isOwnerOrCoDriver($user, $reservation);
    }

    /** Alışı sadece aktif şoför + onaylanmış + henüz alınmamış rezervasyon için yapar. */
    public function pickup(User $user, Reservation $reservation): bool
    {
        return $reservation->aktif_sofor_id === $user->id
            && $reservation->onay_durumu === Reservation::ONAY_ONAYLANDI
            && $reservation->alis_tarihi === null;
    }

    /** Teslimi aktif şoför veya ek şoför yapar (alış tamamlanmış, teslim başvurusu yapılmamış olmalı). */
    public function deliver(User $user, Reservation $reservation): bool
    {
        $isOwner = $this->isOwnerOrCoDriver($user, $reservation);

        return $isOwner
            && $reservation->alis_tarihi !== null
            && $reservation->teslim_basvurusu_tarihi === null;
    }

    /** Onaylama Fleet Manager gate ile; ayrıca beklemede olmalı. */
    public function approve(User $user, Reservation $reservation): bool
    {
        return $user->can('approve-vehicle-request')
            && $reservation->onay_durumu === Reservation::ONAY_BEKLEMEDE;
    }

    public function reject(User $user, Reservation $reservation): bool
    {
        return $this->approve($user, $reservation);
    }

    /** Filo yöneticisi teslim onayı verir. */
    public function confirmDelivery(User $user, Reservation $reservation): bool
    {
        return $user->can('confirm vehicle delivery')
            && $reservation->teslim_basvurusu_tarihi !== null
            && $reservation->teslim_tarihi === null;
    }

    public function forceGarage(User $user, Reservation $reservation): bool
    {
        return $user->can('confirm vehicle delivery')
            && $reservation->onay_durumu === Reservation::ONAY_ONAYLANDI
            && $reservation->teslim_tarihi === null;
    }

    private function isOwnerOrCoDriver(User $user, Reservation $reservation): bool
    {
        if ($reservation->aktif_sofor_id === $user->id) {
            return true;
        }
        $reservation->loadMissing('ekSoforler');

        return $reservation->ekSoforler->contains($user->id);
    }
}
