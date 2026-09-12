<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\PackageType;

class PackageTypeController extends Controller
{
    public function index(): View
    {
        return view('inventory::package-types.index', [
            'packageTypes' => PackageType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, null);

        PackageType::create($validated);

        return redirect()->route('app.inventory.package-types.index')->with('status', __('Package type added.'));
    }

    public function update(Request $request, PackageType $packageType): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, $packageType->id);

        $packageType->update($validated);

        return redirect()->route('app.inventory.package-types.index')->with('status', __('Package type updated.'));
    }

    public function destroy(PackageType $packageType): RedirectResponse
    {
        $packageType->delete();

        return redirect()->route('app.inventory.package-types.index')->with('status', __('Package type deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('package_types', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($ignoreId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('package_types', 'barcode')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($ignoreId),
            ],
            'height' => ['required', 'numeric', 'min:0'],
            'width' => ['required', 'numeric', 'min:0'],
            'packaging_length' => ['required', 'numeric', 'min:0'],
            'max_weight' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
