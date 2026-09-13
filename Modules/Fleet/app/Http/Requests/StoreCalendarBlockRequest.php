<?php

namespace Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\VehicleCalendarBlock;

class StoreCalendarBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage vehicle calendar') ?? false;
    }

    public function rules(): array
    {
        return [
            'block_type' => ['required', Rule::in([
                VehicleCalendarBlock::TYPE_BAKIM,
                VehicleCalendarBlock::TYPE_MUAYENE,
                VehicleCalendarBlock::TYPE_BLOKE,
            ])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'aciklama' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
