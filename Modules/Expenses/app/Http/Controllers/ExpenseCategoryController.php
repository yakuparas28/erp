<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Expenses\Models\ExpenseCategory;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('expenses::categories.index', [
            'categories' => ExpenseCategory::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);
        ExpenseCategory::create($validated);

        return back()->with('status', __('Category added.'));
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);
        $category->update($validated);

        return back()->with('status', __('Category updated.'));
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        if ($category->expenses()->exists()) {
            return back()->withErrors(['category' => __('This category is used by expenses and cannot be deleted.')]);
        }
        $category->delete();

        return back()->with('status', __('Category deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ExpenseCategory $category): array
    {
        $tenantId = $request->user()->tenant_id;

        $v = $request->validate([
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('expense_categories', 'code')->ignore($category?->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:32'],
            'expense_account_code' => ['nullable', 'string', 'max:32'],
            'is_reinvoiceable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $v['is_reinvoiceable'] = $request->boolean('is_reinvoiceable');
        $v['is_active'] = $request->boolean('is_active', true);
        $v['unit_price'] = $v['unit_price'] ?? 0;
        $v['unit_label'] = $v['unit_label'] ?: 'Adet';

        return $v;
    }
}
