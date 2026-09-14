<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $validated = $this->validated($request, null);
        $partner = Partner::create($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name added.', ['name' => $partner->name]));
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $this->validated($request, $partner);
        $partner->update($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name updated.', ['name' => $partner->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Partner $partner): array
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'partner_code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('partners', 'partner_code')
                    ->ignore($partner?->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['nullable', Rule::in([Partner::ENTITY_COMPANY, Partner::ENTITY_INDIVIDUAL])],
            'group_code' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:128'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:64'],
            'district' => ['nullable', 'string', 'max:64'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'tax_office' => ['nullable', 'string', 'max:128'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'e_invoice_status' => ['nullable', Rule::in([Partner::EINVOICE_NONE, Partner::EINVOICE_ARSIV, Partner::EINVOICE_FATURA])],
            'e_invoice_alias' => ['nullable', 'string', 'max:128'],
            'is_customer' => ['nullable', 'boolean'],
            'is_supplier' => ['nullable', 'boolean'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'account_code_receivable' => ['nullable', 'string', 'max:32'],
            'account_code_payable' => ['nullable', 'string', 'max:32'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_customer'] = $request->boolean('is_customer');
        $validated['is_supplier'] = $request->boolean('is_supplier');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['payment_term_days'] = $validated['payment_term_days'] ?? 0;
        $validated['entity_type'] = $validated['entity_type'] ?? Partner::ENTITY_COMPANY;
        $validated['e_invoice_status'] = $validated['e_invoice_status'] ?? Partner::EINVOICE_NONE;
        $validated['country'] = $validated['country'] ?? 'Türkiye';
        $validated['currency_code'] = $validated['currency_code'] ?? 'TRY';
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;

        return $validated;
    }
}
