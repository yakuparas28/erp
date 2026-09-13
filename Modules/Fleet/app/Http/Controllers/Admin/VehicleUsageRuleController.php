<?php

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fleet\Models\VehicleUsageRule;

class VehicleUsageRuleController extends Controller
{
    public function show(): View
    {
        $rule = VehicleUsageRule::forTenant(auth()->user()->tenant_id);

        return view('fleet::admin.vehicle-usage-rules.show', compact('rule'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'icerik_html' => ['nullable', 'string', 'max:65535'],
        ]);

        $rule = VehicleUsageRule::forTenant(auth()->user()->tenant_id);
        $rule->update([
            'icerik_html' => HtmlSanitizer::clean($data['icerik_html'] ?? ''),
            'updated_by_id' => auth()->id(),
        ]);

        return back()->with('success', 'Araç kullanım kuralları güncellendi.');
    }
}
