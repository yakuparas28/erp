<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm own pickup') ?? false;
    }

    public function rules(): array
    {
        return [
            'okunan_km' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
