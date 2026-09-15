<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\Partner;

/**
 * Store + Update için tek FormRequest. Route model binding'den gelen
 * `partner` unique kontrolünde `ignore` için kullanılır.
 */
class PartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage partners') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $partner = $this->route('partner');

        return [
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
        ];
    }

    /**
     * Boolean/default değerleri normalize et — controller'ın validated()'i
     * ile aynı davranışı üretir.
     *
     * @return array<string, mixed>
     */
    public function normalizedData(): array
    {
        $data = $this->validated();
        $data['is_customer'] = $this->boolean('is_customer');
        $data['is_supplier'] = $this->boolean('is_supplier');
        $data['is_active'] = $this->boolean('is_active', true);
        $data['payment_term_days'] = $data['payment_term_days'] ?? 0;
        $data['entity_type'] = $data['entity_type'] ?? Partner::ENTITY_COMPANY;
        $data['e_invoice_status'] = $data['e_invoice_status'] ?? Partner::EINVOICE_NONE;
        $data['country'] = $data['country'] ?? 'Türkiye';
        $data['currency_code'] = $data['currency_code'] ?? 'TRY';
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        return $data;
    }
}
