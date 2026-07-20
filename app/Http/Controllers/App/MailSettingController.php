<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreMailSettingRequest;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('app.settings.mail', [
            'setting' => MailSetting::where('tenant_id', $request->user()->tenant_id)->first(),
        ]);
    }

    public function update(StoreMailSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $setting = MailSetting::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            $data,
        );

        activity()
            ->causedBy($request->user())
            ->performedOn($setting)
            ->withProperties(['tenant_id' => $request->user()->tenant_id])
            ->log('mail_settings.updated');

        return redirect()->route('app.settings.mail')->with('status', 'E-posta ayarları kaydedildi.');
    }
}
