<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Expenses\Http\Requests\ExpenseCategoryRequest;
use Modules\Expenses\Models\ExpenseCategory;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('expenses::categories.index', [
            'categories' => ExpenseCategory::orderBy('code')->get(),
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->normalizedData());

        return back()->with('status', __('Category added.'));
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->normalizedData());

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
}
