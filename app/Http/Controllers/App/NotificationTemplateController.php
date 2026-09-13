<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    /**
     * Süperadmin'in tenant açarken gönderdiği davet template'i platforma
     * özeldir; tenant admin'in düzenleme yetkisi ya da anlamı yok.
     *
     * @var list<string>
     */
    private const PLATFORM_ONLY_KEYS = ['tenant_admin_invitation'];

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $overrides = NotificationTemplate::where('tenant_id', $tenantId)->get()->keyBy('key');

        $templates = NotificationTemplate::whereNull('tenant_id')
            ->whereNotIn('key', self::PLATFORM_ONLY_KEYS)
            ->orderBy('name')->get()
            ->map(fn (NotificationTemplate $default) => [
                'default' => $default,
                'override' => $overrides->get($default->key),
            ]);

        return view('app.settings.notification-templates', ['templates' => $templates]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_if(in_array($key, self::PLATFORM_ONLY_KEYS, true), 404);

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
            ->with('status', __('Template ":name" saved as your company override.', ['name' => $default->name]));
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        abort_if(in_array($key, self::PLATFORM_ONLY_KEYS, true), 404);

        NotificationTemplate::where('tenant_id', $request->user()->tenant_id)
            ->where('key', $key)
            ->delete();

        return redirect()
            ->route('app.settings.templates')
            ->with('status', __('Template reset to the platform default.'));
    }
}
