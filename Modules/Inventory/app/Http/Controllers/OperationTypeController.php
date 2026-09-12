<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\OperationType;
use Modules\Inventory\Models\Warehouse;

class OperationTypeController extends Controller
{
    public function index(): View
    {
        return view('inventory::operation-types.index', [
            'types' => OperationType::with(['warehouse', 'defaultSourceLocation', 'defaultDestinationLocation'])
                ->orderBy('warehouse_id')->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, null);

        OperationType::create($validated + ['active' => true]);

        return redirect()->route('app.inventory.operation-types.index')->with('status', __('Operation type added.'));
    }

    public function update(Request $request, OperationType $operationType): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, $operationType->id);

        $operationType->update($validated);

        return redirect()->route('app.inventory.operation-types.index')->with('status', __('Operation type updated.'));
    }

    public function destroy(OperationType $operationType): RedirectResponse
    {
        $operationType->delete();

        return redirect()->route('app.inventory.operation-types.index')->with('status', __('Operation type deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId): array
    {
        return $request->validate([
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:incoming,outgoing,internal,scrap'],
            'sequence_prefix' => ['nullable', 'string', 'max:16'],
            'default_source_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'default_destination_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);
    }
}
