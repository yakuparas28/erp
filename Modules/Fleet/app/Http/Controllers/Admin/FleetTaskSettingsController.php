<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Modules\Fleet\Console\Commands\SendVehicleCriticalWindowReminders;
use Modules\Fleet\Models\FleetTaskSetting;

class FleetTaskSettingsController extends Controller
{
    public function show(): View
    {
        $setting = FleetTaskSetting::forTenant(auth()->user()->tenant_id);

        return view('fleet::admin.settings.tasks', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'critical_window_enabled' => ['nullable', 'boolean'],
            'critical_window_days' => ['required', 'integer', 'min:1', 'max:60'],
            'mtv_reminder_days' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $setting = FleetTaskSetting::forTenant(auth()->user()->tenant_id);
        $setting->update([
            'critical_window_enabled' => (bool) ($data['critical_window_enabled'] ?? false),
            'critical_window_days' => (int) $data['critical_window_days'],
            'mtv_reminder_days' => (int) $data['mtv_reminder_days'],
        ]);

        return back()->with('success', 'Zamanlanmış görev ayarları güncellendi.');
    }

    public function runCriticalWindow(): RedirectResponse
    {
        Artisan::call(SendVehicleCriticalWindowReminders::class, [
            '--tenant' => auth()->user()->tenant_id,
            '--force' => true,
        ]);

        return back()->with('success', 'Kritik pencere kontrolü tetiklendi.');
    }
}
