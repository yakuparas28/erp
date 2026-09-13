<?php

namespace Modules\Fleet\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\FleetNotificationService;

/**
 * Bakım/muayene kritik pencere hatırlatıcısı. Her tenant için ayrı çalışır;
 * setting `critical_window_enabled=false` ise `--force` verilmedikçe atlanır.
 * Idempotency: gönderilen uyarı için ilgili `*_uyari_son_gonderim` alanı
 * `today` yazılır, aynı gün ikinci kez gönderilmez.
 */
class SendVehicleCriticalWindowReminders extends Command
{
    protected $signature = 'fleet:send-critical-window-reminders {--tenant= : Sadece belirli tenant} {--force : Setting disabled olsa da gönder}';

    protected $description = 'Bakım/muayene kritik pencere e-postalarını gönderir';

    public function handle(FleetNotificationService $notifier): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::whereKey($this->option('tenant'))->get()
            : Tenant::all();

        $totalSent = 0;
        foreach ($tenants as $tenant) {
            $totalSent += $this->handleForTenant($tenant, $notifier);
        }
        $this->info("Kritik pencere kontrolü tamam. Toplam gönderilen: {$totalSent}");

        return self::SUCCESS;
    }

    private function handleForTenant(Tenant $tenant, FleetNotificationService $notifier): int
    {
        $setting = FleetTaskSetting::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id]);
        if (! $setting->critical_window_enabled && ! $this->option('force')) {
            return 0;
        }

        $today = today();
        $window = $today->copy()->addDays($setting->critical_window_days);
        $sent = 0;

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get();

        foreach ($vehicles as $v) {
            if ($v->bakim_tarihi && $v->bakim_tarihi->between($today, $window)
                && ($v->bakim_uyari_son_gonderim === null || $v->bakim_uyari_son_gonderim->lt($today))
            ) {
                $notifier->criticalWindow($v, 'Bakım', $v->bakim_tarihi, (int) $today->diffInDays($v->bakim_tarihi, false));
                $v->update(['bakim_uyari_son_gonderim' => $today]);
                $sent++;
            }
            if ($v->muayene_tarihi && $v->muayene_tarihi->between($today, $window)
                && ($v->muayene_uyari_son_gonderim === null || $v->muayene_uyari_son_gonderim->lt($today))
            ) {
                $notifier->criticalWindow($v, 'Muayene', $v->muayene_tarihi, (int) $today->diffInDays($v->muayene_tarihi, false));
                $v->update(['muayene_uyari_son_gonderim' => $today]);
                $sent++;
            }
        }

        $setting->update([
            'critical_window_last_run_at' => now(),
            'critical_window_last_run_notified' => $sent,
        ]);

        return $sent;
    }
}
