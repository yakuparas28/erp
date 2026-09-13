<?php

namespace Modules\Fleet\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * MTV kontrolü. İki yol:
 *  a) Yaklaşan (N gün içinde): mtv_odeme_durumu Odenmedi/Kismi_Odendi
 *  b) Gecikmiş: mtv_odeme_tarihi geçmişte, hâlâ Odenmedi
 * Aynı gün ikinci gönderim önlenir (`mtv_uyari_son_gonderim`).
 */
class CheckVehicleMtv extends Command
{
    protected $signature = 'fleet:check-mtv {--tenant=}';

    protected $description = 'MTV uyarı e-postalarını (yaklaşan ve gecikmiş) gönderir';

    public function handle(FleetNotificationService $notifier): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::whereKey($this->option('tenant'))->get()
            : Tenant::all();

        $totalSent = 0;
        foreach ($tenants as $tenant) {
            $totalSent += $this->handleForTenant($tenant, $notifier);
        }
        $this->info("MTV kontrolü tamam. Toplam gönderilen: {$totalSent}");

        return self::SUCCESS;
    }

    private function handleForTenant(Tenant $tenant, FleetNotificationService $notifier): int
    {
        $setting = FleetTaskSetting::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id]);
        $today = today();
        $windowEnd = $today->copy()->addDays($setting->mtv_reminder_days);
        $sent = 0;

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('mtv_odeme_tarihi')
            ->get();

        foreach ($vehicles as $v) {
            $alreadySentToday = $v->mtv_uyari_son_gonderim !== null && $v->mtv_uyari_son_gonderim->gte($today);
            if ($alreadySentToday) {
                continue;
            }

            if ($v->mtv_odeme_tarihi->lt($today) && $v->mtv_odeme_durumu === Vehicle::MTV_ODENMEDI) {
                $gecikme = (int) $v->mtv_odeme_tarihi->diffInDays($today);
                $notifier->mtvOverdue($v, $gecikme);
                $v->update(['mtv_uyari_son_gonderim' => $today]);
                $sent++;

                continue;
            }

            if ($v->mtv_odeme_tarihi->between($today, $windowEnd)
                && in_array($v->mtv_odeme_durumu, [Vehicle::MTV_ODENMEDI, Vehicle::MTV_KISMI], true)
            ) {
                $kalan = (int) $today->diffInDays($v->mtv_odeme_tarihi, false);
                $notifier->mtv30Days($v, $kalan);
                $v->update(['mtv_uyari_son_gonderim' => $today]);
                $sent++;
            }
        }

        $setting->update(['mtv_last_run_at' => now()]);

        return $sent;
    }
}
