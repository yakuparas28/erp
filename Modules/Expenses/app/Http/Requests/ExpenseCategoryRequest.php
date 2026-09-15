<?php

namespace Modules\Expenses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage expense categories') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $category = $this->route('category');

        return [
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('expense_categories', 'code')
                    ->ignore($category?->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:32'],
            'expense_account_code' => ['nullable', 'string', 'max:32'],
            'is_reinvoiceable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function normalizedData(): array
    {
        $v = $this->validated();
        $v['is_reinvoiceable'] = $this->boolean('is_reinvoiceable');
        $v['is_active'] = $this->boolean('is_active', true);
        $v['unit_price'] = $v['unit_price'] ?? 0;
        $v['unit_label'] = ($v['unit_label'] ?? '') ?: 'Adet';

        return $v;
    }
}
