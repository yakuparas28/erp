<?php

namespace App\Http\Controllers\Central\Web;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        return view('central.settings.notification-templates', [
            'templates' => NotificationTemplate::whereNull('tenant_id')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $template->update($validated);

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($template)
            ->log('notification_template.updated');

        return redirect()
            ->route('central.web.settings.templates')
            ->with('status', "\"{$template->name}\" şablonu güncellendi.");
    }
}
