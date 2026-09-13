<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit own delivery') ?? false;
    }

    public function rules(): array
    {
        $reservation = $this->route('reservation');
        $min = (int) ($reservation?->alis_km ?? 0);

        return [
            'teslim_km' => ['required', 'integer', "min:{$min}", 'max:9999999'],
            'teslim_beyani' => ['required', 'boolean'],
            'teslim_ariza_aciklamasi' => ['nullable', 'string', 'max:2000', 'required_if:teslim_beyani,0'],
            'teslim_foto_on' => ['required', 'image', 'max:8192'],
            'teslim_foto_arka' => ['required', 'image', 'max:8192'],
            'teslim_foto_sag' => ['required', 'image', 'max:8192'],
            'teslim_foto_sol' => ['required', 'image', 'max:8192'],
            'teslim_foto_km' => ['required', 'image', 'max:8192'],
        ];
    }
}
