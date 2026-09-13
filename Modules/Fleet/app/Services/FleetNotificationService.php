<?php

namespace Modules\Fleet\Services;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Fleet\Models\MaintenanceRecord;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Throwable;

/**
 * Fleet akışının dış-etki tarafı: rezervasyon/onay/teslim/blok/kritik
 * pencere/MTV olaylarında ilgili taraflara mail atar. LeaveNotificationService
 * ile aynı pattern; iş servisi ve controller'lar buraya delege eder.
 */
class FleetNotificationService
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TenantMailer $mailer,
    ) {}

    public function reservationCreated(Reservation $reservation): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor', 'project']);
        $vars = $this->reservationVars($reservation);
        $vars['onay_baglantisi'] = route('app.fleet.approvals.index');

        foreach ($this->fleetManagers($reservation->tenant_id) as $user) {
            $this->send($reservation->tenant_id, $user->email, 'fleet_reservation_created', $vars);
        }
    }

    public function reservationApproved(Reservation $reservation): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor', 'project', 'ekSoforler']);
        $vars = $this->reservationVars($reservation);
        $vars['alis_baglantisi'] = route('app.fleet.reservations.pickup', $reservation);

        $recipients = collect([$reservation->aktifSofor])->merge($reservation->ekSoforler)->filter();
        foreach ($recipients as $user) {
            $this->send($reservation->tenant_id, $user->email, 'fleet_reservation_approved', $vars + [
                'recipient_name' => $user->name,
            ]);
        }
    }

    public function reservationRejected(Reservation $reservation, ?string $reason): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor', 'project']);
        $vars = $this->reservationVars($reservation) + [
            'recipient_name' => $reservation->aktifSofor?->name ?? '',
            'red_aciklamasi' => (string) ($reason ?? '—'),
        ];
        if ($reservation->aktifSofor) {
            $this->send($reservation->tenant_id, $reservation->aktifSofor->email, 'fleet_reservation_rejected', $vars);
        }
    }

    public function pickupConfirmed(Reservation $reservation): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor']);
        $vars = $this->reservationVars($reservation) + [
            'alis_km' => (string) ($reservation->alis_km ?? '—'),
            'alis_tarihi' => $reservation->alis_tarihi?->format('d.m.Y H:i') ?? '—',
        ];
        foreach ($this->fleetManagers($reservation->tenant_id) as $u) {
            $this->send($reservation->tenant_id, $u->email, 'fleet_pickup_confirmed', $vars);
        }
    }

    public function mileageDeviation(Reservation $reservation, int $sistemKm, int $okunanKm, int $sapma): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor']);
        $vars = $this->reservationVars($reservation) + [
            'sistem_km' => (string) $sistemKm,
            'okunan_km' => (string) $okunanKm,
            'sapma' => ($sapma > 0 ? '+' : '').$sapma,
        ];
        foreach ($this->fleetManagers($reservation->tenant_id) as $u) {
            $this->send($reservation->tenant_id, $u->email, 'fleet_mileage_deviation', $vars);
        }
    }

    public function deliveryCompleted(Reservation $reservation): void
    {
        $reservation->loadMissing(['vehicle', 'aktifSofor']);
        $vars = $this->reservationVars($reservation) + [
            'teslim_km' => (string) ($reservation->teslim_km ?? '—'),
            'teslim_tarihi' => $reservation->teslim_tarihi?->format('d.m.Y H:i') ?? '—',
            'beyan_durumu' => $reservation->teslim_beyani ? 'Sorunsuz' : 'Arızalı',
        ];
        foreach ($this->fleetManagers($reservation->tenant_id) as $u) {
            $this->send($reservation->tenant_id, $u->email, 'fleet_delivery_completed', $vars);
        }
    }

    public function vehicleBlocked(Vehicle $vehicle, ?User $blockedBy = null): void
    {
        $vars = [
            'plaka' => $vehicle->plaka,
            'marka_model' => (string) $vehicle->marka_model,
            'blocked_by' => $blockedBy?->name ?? '—',
            'blocked_at' => now()->format('d.m.Y H:i'),
        ];
        foreach ($this->fleetManagers($vehicle->tenant_id) as $u) {
            $this->send($vehicle->tenant_id, $u->email, 'fleet_vehicle_blocked', $vars);
        }
    }

    public function vehicleUnblocked(MaintenanceRecord $record): void
    {
        $record->loadMissing(['vehicle']);
        $vars = [
            'plaka' => $record->vehicle?->plaka ?? '—',
            'marka_model' => (string) ($record->vehicle?->marka_model ?? ''),
            'islem_turu' => $record->islem_turu,
            'yapilan_islemler' => (string) $record->yapilan_islemler,
            'yeni_bakim_tarihi' => $record->yeni_bakim_tarihi?->format('d.m.Y') ?? '—',
            'yeni_muayene_tarihi' => $record->yeni_muayene_tarihi?->format('d.m.Y') ?? '—',
        ];
        foreach ($this->fleetManagers($record->tenant_id) as $u) {
            $this->send($record->tenant_id, $u->email, 'fleet_vehicle_unblocked', $vars);
        }
    }

    public function criticalWindow(Vehicle $vehicle, string $tur, Carbon $hedefTarih, int $kalanGun): void
    {
        $vars = [
            'plaka' => $vehicle->plaka,
            'marka_model' => (string) $vehicle->marka_model,
            'tur' => $tur,
            'hedef_tarih' => $hedefTarih->format('d.m.Y'),
            'kalan_gun' => (string) $kalanGun,
        ];
        foreach ($this->fleetManagers($vehicle->tenant_id) as $u) {
            $this->send($vehicle->tenant_id, $u->email, 'fleet_vehicle_critical_window', $vars);
        }
    }

    public function mtv30Days(Vehicle $vehicle, int $kalanGun): void
    {
        $vars = [
            'plaka' => $vehicle->plaka,
            'marka_model' => (string) $vehicle->marka_model,
            'mtv_tarih' => $vehicle->mtv_odeme_tarihi?->format('d.m.Y') ?? '—',
            'kalan_gun' => (string) $kalanGun,
        ];
        foreach ($this->fleetManagers($vehicle->tenant_id) as $u) {
            $this->send($vehicle->tenant_id, $u->email, 'fleet_vehicle_mtv_30_days', $vars);
        }
    }

    public function mtvOverdue(Vehicle $vehicle, int $gecikmeGun): void
    {
        $vars = [
            'plaka' => $vehicle->plaka,
            'marka_model' => (string) $vehicle->marka_model,
            'mtv_tarih' => $vehicle->mtv_odeme_tarihi?->format('d.m.Y') ?? '—',
            'gecikme_gun' => (string) $gecikmeGun,
        ];
        foreach ($this->fleetManagers($vehicle->tenant_id) as $u) {
            $this->send($vehicle->tenant_id, $u->email, 'fleet_vehicle_mtv_overdue', $vars);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function fleetManagers(int $tenantId): Collection
    {
        setPermissionsTeamId($tenantId);

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Fleet Manager'))
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function reservationVars(Reservation $r): array
    {
        return [
            'reservation_id' => str_pad((string) $r->id, 5, '0', STR_PAD_LEFT),
            'aktif_sofor' => $r->aktifSofor?->name ?? '—',
            'plaka' => $r->vehicle?->plaka ?? '—',
            'marka_model' => (string) ($r->vehicle?->marka_model ?? ''),
            'proje' => $r->project?->ad ?? '—',
            'planlanan_alis' => $r->planlanan_alis_at?->format('d.m.Y H:i') ?? '—',
            'planlanan_teslim' => $r->planlanan_teslim_at?->format('d.m.Y H:i') ?? '—',
            'talep_tarihi' => $r->talep_tarihi?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    private function send(int $tenantId, string $to, string $key, array $vars): void
    {
        try {
            $rendered = $this->templates->render($key, $tenantId, array_merge([
                'firma_adi' => Tenant::find($tenantId)?->name ?? '',
                'uygulama_adi' => config('app.name'),
            ], $vars));
            $this->mailer->send($tenantId, $to, new TemplatedMail($rendered['subject'], $rendered['body']));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
