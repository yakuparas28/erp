<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Warehouse;

class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('inventory::warehouses.index', [
            'warehouses' => Warehouse::with(['locations' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $warehouse = Warehouse::create($validated);

        activity()->causedBy($request->user())->performedOn($warehouse)->log('warehouse.created');

        return redirect()
            ->route('app.inventory.warehouses.index')
            ->with('status', __(':name added.', ['name' => $warehouse->name]));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reception_steps' => ['required', 'in:one_step,two_step,three_step'],
            'delivery_steps' => ['required', 'in:one_step,two_step,three_step'],
            'input_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)),
            ],
            'quality_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)),
            ],
            'output_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)),
            ],
            'pack_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)),
            ],
        ]);

        if ($validated['reception_steps'] !== 'one_step' && empty($validated['input_location_id'])) {
            return back()->withErrors(['input_location_id' => __('Input location is required for multi-step reception.')]);
        }
        if ($validated['reception_steps'] === 'three_step' && empty($validated['quality_location_id'])) {
            return back()->withErrors(['quality_location_id' => __('Quality location is required for three-step reception.')]);
        }
        if ($validated['delivery_steps'] !== 'one_step' && empty($validated['output_location_id'])) {
            return back()->withErrors(['output_location_id' => __('Output location is required for multi-step delivery.')]);
        }
        if ($validated['delivery_steps'] === 'three_step' && empty($validated['pack_location_id'])) {
            return back()->withErrors(['pack_location_id' => __('Pack location is required for three-step delivery.')]);
        }

        $warehouse->update($validated);

        return redirect()->route('app.inventory.warehouses.index')->with('status', __(':name updated.', ['name' => $warehouse->name]));
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'parent_id' => ['nullable', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $validated['type'] = 'internal';

        $location = Location::create($validated);

        activity()->causedBy($request->user())->performedOn($location)->log('location.created');

        return redirect()
            ->route('app.inventory.warehouses.index')
            ->with('status', __(':name added.', ['name' => $location->name]));
    }
}
