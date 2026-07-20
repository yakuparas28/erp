<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $overrides = NotificationTemplate::where('tenant_id', $tenantId)->get()->keyBy('key');

        $templates = NotificationTemplate::whereNull('tenant_id')->orderBy('name')->get()
            ->map(fn (NotificationTemplate $default) => [
                'default' => $default,
                'override' => $overrides->get($default->key),
            ]);

        return view('app.settings.notification-templates', ['templates' => $templates]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $default = NotificationTemplate::whereNull('tenant_id')->where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $template = NotificationTemplate::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id, 'key' => $key],
            [...$validated, 'name' => $default->name],
        );

        activity()
            ->causedBy($request->user())
            ->performedOn($template)
            ->log('notification_template.updated');

        return redirect()
            ->route('app.settings.templates')
            ->with('status', "\"{$default->name}\" şablonu firmanıza özel olarak kaydedildi.");
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        NotificationTemplate::where('tenant_id', $request->user()->tenant_id)
            ->where('key', $key)
            ->delete();

        return redirect()
            ->route('app.settings.templates')
            ->with('status', 'Şablon platform varsayılanına döndürüldü.');
    }
}
