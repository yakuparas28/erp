<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Inventory\Models\ProductCategory;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('inventory::categories.index', [
            'categories' => ProductCategory::with('children', 'parent')->orderBy('name')->get(),
            'accounts' => class_exists(ChartOfAccount::class)
                ? ChartOfAccount::orderBy('code')->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'stock_input_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'stock_output_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'expense_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'income_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        ProductCategory::create($validated);

        return redirect()->route('app.inventory.categories.index')->with('status', __('Category added.'));
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'stock_input_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'stock_output_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'expense_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'income_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        if ($validated['parent_id'] === (string) $category->id || (int) ($validated['parent_id'] ?? 0) === $category->id) {
            return back()->withErrors(['parent_id' => __('A category cannot be its own parent.')]);
        }

        $category->update($validated);

        return redirect()->route('app.inventory.categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['category' => __('Delete the sub-categories first.')]);
        }

        if ($category->products()->exists()) {
            return back()->withErrors(['category' => __('This category is used by products and cannot be deleted.')]);
        }

        $category->delete();

        return redirect()->route('app.inventory.categories.index')->with('status', __('Category deleted.'));
    }
}
