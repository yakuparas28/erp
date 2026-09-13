@php use Modules\Fleet\Models\VehicleCalendarBlock; @endphp
@extends('app.layouts.app')

@section('title', $vehicle->plaka)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-bold">{{ $vehicle->plaka }} <span class="text-sm text-default font-normal">{{ $vehicle->marka_model }}</span></h1>
    <div class="flex gap-2">
        <button type="button" data-hs-overlay="#block-modal" class="btn-sm bg-primary text-white"><i class="ph ph-plus"></i> {{ __('New Block') }}</button>
        <a href="{{ route('app.fleet.vehicle-calendar.index') }}" class="btn-sm border border-border-color">{{ __('Back to List') }}</a>
    </div>
</div>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="font-semibold mb-3">{{ __('Upcoming Blocks') }}</div>
        @forelse ($upcomingBlocks as $b)
            <div class="flex justify-between items-center py-2 border-b border-border-color last:border-0">
                <div>
                    <div class="text-sm font-medium">{{ __('block-type.'.$b->block_type) }}</div>
                    <div class="text-xs text-default">{{ $b->start_date->format('d.m.Y') }} → {{ $b->end_date->format('d.m.Y') }}</div>
                    @if ($b->aciklama)<div class="text-xs text-default mt-1">{{ $b->aciklama }}</div>@endif
                </div>
                <form method="POST" action="{{ route('app.fleet.vehicle-calendar.blocks.destroy', $b) }}" onsubmit="return confirm('{{ __('Delete?') }}');">
                    @csrf @method('DELETE')
                    <button class="btn-sm text-danger border border-danger"><i class="ph ph-trash"></i></button>
                </form>
            </div>
        @empty
            <div class="text-sm text-default">{{ __('No records.') }}</div>
        @endforelse
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="font-semibold mb-3">{{ __('Planned Reservations') }}</div>
        @forelse ($plannedReservations as $r)
            <div class="py-2 border-b border-border-color last:border-0">
                <div class="text-sm">{{ $r->aktifSofor?->name }} · <span class="text-default">{{ $r->project?->ad ?? '—' }}</span></div>
                <div class="text-xs text-default">{{ $r->planlanan_alis_at->format('d.m.Y H:i') }} → {{ $r->planlanan_teslim_at->format('d.m.Y H:i') }}</div>
            </div>
        @empty
            <div class="text-sm text-default">{{ __('No records.') }}</div>
        @endforelse
    </div>
</div>

<div class="mt-4 bg-white border border-border-color rounded-md p-4">
    <div class="font-semibold mb-3">{{ __('Usage History') }}</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-border-color text-left">
                <tr>
                    <th class="p-2">{{ __('Requester') }}</th>
                    <th class="p-2">{{ __('Project') }}</th>
                    <th class="p-2">{{ __('Pickup Datetime') }}</th>
                    <th class="p-2">{{ __('Return Datetime') }}</th>
                    <th class="p-2">KM</th>
                    <th class="p-2">{{ __('Closure') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usageHistory as $r)
                    <tr class="border-b border-border-color {{ $r->closure_reason === 'filo_garaja_cekti' ? 'bg-warning-transparent' : '' }}">
                        <td class="p-2">{{ $r->aktifSofor?->name }}</td>
                        <td class="p-2">{{ $r->project?->ad ?? '—' }}</td>
                        <td class="p-2">{{ $r->alis_tarihi?->format('d.m.Y H:i') }}</td>
                        <td class="p-2">{{ $r->teslim_tarihi?->format('d.m.Y H:i') }}</td>
                        <td class="p-2">{{ number_format($r->alis_km ?? 0) }} → {{ number_format($r->teslim_km ?? 0) }}</td>
                        <td class="p-2"><span class="text-xs {{ $r->closure_reason === 'filo_garaja_cekti' ? 'text-warning font-semibold' : 'text-success' }}">{{ $r->closure_reason === 'filo_garaja_cekti' ? 'İstisnai' : 'Normal' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-default">{{ __('No records.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="block-modal" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[70] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto flex items-center min-h-[calc(100%-56px)]">
        <div class="w-full bg-white border rounded-xl pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b"><h3 class="font-bold">{{ __('New Block') }}</h3><button type="button" data-hs-overlay="#block-modal"><i class="ph ph-x"></i></button></div>
            <form method="POST" action="{{ route('app.fleet.vehicle-calendar.blocks.store', $vehicle) }}">
                @csrf
                <div class="p-4 grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="text-xs text-default">Tip</label>
                        <select name="block_type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                            <option value="{{ VehicleCalendarBlock::TYPE_BAKIM }}">{{ __('block-type.bakim') }}</option>
                            <option value="{{ VehicleCalendarBlock::TYPE_MUAYENE }}">{{ __('block-type.muayene') }}</option>
                            <option value="{{ VehicleCalendarBlock::TYPE_BLOKE }}">{{ __('block-type.bloke') }}</option>
                        </select>
                    </div>
                    <div><label class="text-xs text-default">{{ __('Start Date') }}</label><input name="start_date" type="date" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('End Date') }}</label><input name="end_date" type="date" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div class="col-span-2"><label class="text-xs text-default">{{ __('Description') }}</label><textarea name="aciklama" rows="3" maxlength="1000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></textarea></div>
                </div>
                <div class="flex justify-end gap-2 py-3 px-4 border-t"><button type="button" class="btn-sm border border-border-color" data-hs-overlay="#block-modal">{{ __('Cancel') }}</button><button class="btn-sm bg-primary text-white">{{ __('Save') }}</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
