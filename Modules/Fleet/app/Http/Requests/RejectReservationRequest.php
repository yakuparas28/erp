<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve-vehicle-request') ?? false;
    }

    public function rules(): array
    {
        return [
            'red_aciklamasi' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
