<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Services\ReorderingService;

class ReorderingRuleController extends Controller
{
    public function __construct(private readonly ReorderingService $reordering) {}

    public function index(): View
    {
        return view('inventory::reordering.index', [
            'rules' => ReorderingRule::with(['product', 'location'])->get(),
            'suggestions' => ReplenishmentSuggestion::with('reorderingRule.product')->where('status', 'pending')->latest()->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('reordering_rules', 'product_id')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('location_id', $request->input('location_id')),
            ],
            'location_id' => ['required', 'exists:locations,id'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['required', 'numeric', 'gt:min_qty'],
            'trigger_type' => ['required', 'in:auto,manual'],
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        ReorderingRule::create($validated);

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Reordering rule added.'));
    }

    public function destroy(ReorderingRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Reordering rule deleted.'));
    }

    public function acknowledge(Request $request, ReplenishmentSuggestion $suggestion): RedirectResponse
    {
        $this->reordering->acknowledge($suggestion, $request->user());

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Suggestion acknowledged; a draft purchase order may have been created if the product has a default supplier.'));
    }
}
