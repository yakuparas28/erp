<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\Vehicle;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage vehicles') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'plaka' => [
                'required', 'string', 'max:16',
                Rule::unique('vehicles', 'plaka')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'marka_model' => ['required', 'string', 'max:128'],
            'yil' => ['nullable', 'integer', 'between:1980,'.(now()->year + 1)],
            'sasi_no' => ['nullable', 'string', 'max:64'],
            'motor_no' => ['nullable', 'string', 'max:64'],
            'guncel_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'bakim_tarihi' => ['nullable', 'date'],
            'muayene_tarihi' => ['nullable', 'date'],
            'durum' => ['required', Rule::in([Vehicle::DURUM_GARAJDA, Vehicle::DURUM_AKTIF, Vehicle::DURUM_BLOKELI])],
            'mtv_odeme_tarihi' => ['nullable', 'date'],
            'mtv_odeme_durumu' => ['nullable', Rule::in([Vehicle::MTV_ODENDI, Vehicle::MTV_ODENMEDI, Vehicle::MTV_KISMI])],
            'mtv_odeme_notu' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
