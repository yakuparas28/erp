<?php

namespace Modules\Expenses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefuseExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve expense') ?? false;
    }

    public function rules(): array
    {
        return [
            'refuse_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
