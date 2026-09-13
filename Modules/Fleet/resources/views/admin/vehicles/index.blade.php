@extends('app.layouts.app')

@section('title', __('Vehicles'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Vehicles') }}</h1>
    <button type="button" data-hs-overlay="#vehicle-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover">
        <i class="ph ph-plus"></i> {{ __('New Vehicle') }}
    </button>
</div>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">{{ __('Plate') }}</th>
                <th class="p-3">{{ __('Brand & Model') }}</th>
                <th class="p-3">{{ __('Year') }}</th>
                <th class="p-3">{{ __('KM') }}</th>
                <th class="p-3">{{ __('Maintenance') }}</th>
                <th class="p-3">{{ __('Inspection') }}</th>
                <th class="p-3">MTV</th>
                <th class="p-3">{{ __('Status') }}</th>
                <th class="p-3 text-right">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vehicles as $v)
                @php
                    $mtvBadge = match ($v->mtv_odeme_durumu) {
                        'Odendi' => 'bg-success-transparent text-success',
                        'Kismi_Odendi' => 'bg-warning-transparent text-warning',
                        default => ($v->mtv_odeme_tarihi && $v->mtv_odeme_tarihi->isPast()) ? 'bg-danger-transparent text-danger font-bold' : 'bg-gray-100 text-default',
                    };
                    $statusBadge = match ($v->durum) {
                        'Aktif_Kullanimda' => 'bg-primary text-white',
                        'Blokeli_Bakimda' => 'bg-danger text-white',
                        default => 'bg-success text-white',
                    };
                @endphp
                <tr class="border-b border-border-color">
                    <td class="p-3 font-semibold">{{ $v->plaka }}</td>
                    <td class="p-3">{{ $v->marka_model }}</td>
                    <td class="p-3">{{ $v->yil }}</td>
                    <td class="p-3">{{ number_format($v->guncel_km) }}</td>
                    <td class="p-3">{{ $v->bakim_tarihi?->format('d.m.Y') ?? '—' }}</td>
                    <td class="p-3">{{ $v->muayene_tarihi?->format('d.m.Y') ?? '—' }}</td>
                    <td class="p-3"><span class="{{ $mtvBadge }} px-2 py-1 rounded text-xs">{{ __('mtv.'.($v->mtv_odeme_durumu ?? 'Odenmedi')) }}</span></td>
                    <td class="p-3"><span class="{{ $statusBadge }} px-2 py-1 rounded text-xs">{{ __('vehicle-status.'.$v->durum) }}</span></td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <button type="button" class="btn-sm border border-border-color" onclick='fillVehicleForm(@json($v))' data-hs-overlay="#vehicle-modal"><i class="ph ph-pencil-simple"></i></button>
                        <form method="POST" action="{{ route('app.fleet.vehicles.destroy', $v) }}" class="inline" onsubmit="return confirm('{{ __('Delete this vehicle?') }}');">
                            @csrf @method('DELETE')
                            <button class="btn-sm text-danger border border-danger"><i class="ph ph-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-6 text-center text-default">{{ __('No records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $vehicles->links() }}</div>

@include('fleet::admin.vehicles._vehicle-modal')

<script>
function fillVehicleForm(v) {
    const f = document.getElementById('vehicle-form');
    f.action = `{{ url('app/fleet/vehicles') }}/${v.id}`;
    f.querySelector('input[name="_method"]').value = 'PUT';
    for (const k of ['plaka','marka_model','yil','sasi_no','motor_no','guncel_km','bakim_tarihi','muayene_tarihi','durum','mtv_odeme_tarihi','mtv_odeme_durumu','mtv_odeme_notu']) {
        const el = f.querySelector(`[name="${k}"]`);
        if (el) el.value = v[k] ?? '';
    }
}
document.getElementById('vehicle-modal-new-btn')?.addEventListener('click', () => {
    const f = document.getElementById('vehicle-form');
    f.reset();
    f.action = "{{ route('app.fleet.vehicles.store') }}";
    f.querySelector('input[name="_method"]').value = 'POST';
});
</script>
@endsection
