<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Partner;

class PartnerController extends Controller
{
    public function index(): View
    {
        return view('inventory::partners.index', [
            'partners' => Partner::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $partner = Partner::create($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name added.', ['name' => $partner->name]));
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $this->validated($request);

        $partner->update($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name updated.', ['name' => $partner->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'is_customer' => ['nullable', 'boolean'],
            'is_supplier' => ['nullable', 'boolean'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_customer'] = $request->boolean('is_customer');
        $validated['is_supplier'] = $request->boolean('is_supplier');
        $validated['payment_term_days'] = $validated['payment_term_days'] ?? 0;

        return $validated;
    }
}
