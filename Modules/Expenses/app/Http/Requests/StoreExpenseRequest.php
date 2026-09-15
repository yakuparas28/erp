<?php

namespace Modules\Expenses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit own expense') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'paid_by' => ['required', Rule::in(['employee', 'company'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'reference' => ['nullable', 'string', 'max:64'],
            'receipt' => ['nullable', 'image', 'max:8192'],
        ];
    }
}
