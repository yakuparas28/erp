<?php

namespace App\Http\Controllers\Central\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreMailSettingRequest;
use App\Models\MailSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    public function edit(): View
    {
        return view('central.settings.mail', [
            'setting' => MailSetting::whereNull('tenant_id')->first(),
        ]);
    }

    public function update(StoreMailSettingRequest $request): RedirectResponse
    {
        $this->save(null, $request);

        return redirect()->route('central.web.settings.mail')->with('status', 'Platform e-posta ayarları kaydedildi.');
    }

    public function updateForTenant(StoreMailSettingRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->save($tenant->id, $request);

        return redirect()
            ->route('central.web.tenants.show', $tenant)
            ->with('status', "{$tenant->name} e-posta ayarları kaydedildi.");
    }

    private function save(?int $tenantId, StoreMailSettingRequest $request): void
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        // Şifre boş bırakıldıysa mevcut şifre korunur.
        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $setting = MailSetting::updateOrCreate(['tenant_id' => $tenantId], $data);

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($setting)
            ->withProperties(['tenant_id' => $tenantId])
            ->log('mail_settings.updated');
    }
}
