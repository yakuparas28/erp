<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\StorageCategory;

class StorageCategoryController extends Controller
{
    public function index(): View
    {
        return view('inventory::storage-categories.index', [
            'categories' => StorageCategory::withCount('locations')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, null);

        StorageCategory::create($validated);

        return redirect()->route('app.inventory.storage-categories.index')->with('status', __('Storage category added.'));
    }

    public function update(Request $request, StorageCategory $storageCategory): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validated($request, $tenantId, $storageCategory->id);

        $storageCategory->update($validated);

        return redirect()->route('app.inventory.storage-categories.index')->with('status', __('Storage category updated.'));
    }

    public function destroy(StorageCategory $storageCategory): RedirectResponse
    {
        if ($storageCategory->locations()->exists()) {
            return back()->withErrors(['storage_category' => __('This storage category is assigned to locations and cannot be deleted.')]);
        }

        $storageCategory->delete();

        return redirect()->route('app.inventory.storage-categories.index')->with('status', __('Storage category deleted.'));
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
                Rule::unique('storage_categories', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($ignoreId),
            ],
            'max_weight' => ['required', 'numeric', 'min:0'],
            'allow_new_product' => ['required', 'in:empty,same,mixed'],
        ]);
    }
}
