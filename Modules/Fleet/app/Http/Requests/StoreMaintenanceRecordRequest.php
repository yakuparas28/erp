<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\MaintenanceRecord;

class StoreMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage maintenance records') ?? false;
    }

    public function rules(): array
    {
        return [
            'islem_turu' => ['required', Rule::in([MaintenanceRecord::ISLEM_BAKIM, MaintenanceRecord::ISLEM_MUAYENE])],
            'yapilan_islemler' => ['required', 'string', 'max:2000'],
            'degisen_parcalar' => ['nullable', 'string', 'max:2000'],
            'yeni_bakim_tarihi' => ['nullable', 'date'],
            'yeni_muayene_tarihi' => ['nullable', 'date'],
        ];
    }
}
