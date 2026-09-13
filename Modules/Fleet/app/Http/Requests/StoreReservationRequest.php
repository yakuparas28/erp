<?php

namespace Modules\Fleet\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Services\AvailableVehicleFinder;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reserve vehicle') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'planlanan_alis_at' => ['required', 'date', 'after:now'],
            'planlanan_teslim_at' => ['required', 'date', 'after:planlanan_alis_at'],
            'arac_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'proje_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('aktif', true)),
            ],
            'ek_soforler' => ['nullable', 'array', 'max:6'],
            'ek_soforler.*' => [
                'integer',
                'different:aktif_sofor_id',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $pickup = CarbonImmutable::parse($this->input('planlanan_alis_at'));
            $return = CarbonImmutable::parse($this->input('planlanan_teslim_at'));
            $available = app(AvailableVehicleFinder::class)->available($pickup, $return);
            if (! $available->contains(fn (Vehicle $x) => $x->id === (int) $this->input('arac_id'))) {
                $v->errors()->add('arac_id', 'Seçtiğiniz araç bu tarih aralığında müsait değil.');
            }
        });
    }
}
