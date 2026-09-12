<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Sales\Models\DeliveryCarrier;

class DeliveryCarrierController extends Controller
{
    public function index(): View
    {
        return view('sales::carriers.index', [
            'carriers' => DeliveryCarrier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('delivery_carriers', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => ['nullable', 'string', 'max:32'],
            'tracking_url_template' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\//i'],
        ]);

        DeliveryCarrier::create($validated + ['active' => true]);

        return redirect()->route('app.sales.carriers.index')->with('status', __('Carrier added.'));
    }

    public function update(Request $request, DeliveryCarrier $carrier): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('delivery_carriers', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId))->ignore($carrier->id),
            ],
            'code' => ['nullable', 'string', 'max:32'],
            'tracking_url_template' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\//i'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $carrier->update($validated + ['active' => (bool) ($validated['active'] ?? $carrier->active)]);

        return redirect()->route('app.sales.carriers.index')->with('status', __('Carrier updated.'));
    }

    public function destroy(DeliveryCarrier $carrier): RedirectResponse
    {
        $carrier->delete();

        return redirect()->route('app.sales.carriers.index')->with('status', __('Carrier deleted.'));
    }
}
