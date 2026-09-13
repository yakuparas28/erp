@extends('app.layouts.app')

@section('title', __('New Reservation'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('New Reservation') }}</h1>
    <a href="{{ route('app.fleet.reservations.index') }}" class="btn-sm border border-border-color">{{ __('Back to List') }}</a>
</div>

@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif
@if ($searchError)
    <div class="bg-warning-transparent text-warning border border-warning rounded-md px-4 py-3 text-sm mb-4">{{ $searchError }}</div>
@endif

<form method="GET" action="{{ route('app.fleet.reservations.create') }}" class="bg-white border border-border-color rounded-md p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="text-xs text-default block mb-1">{{ __('Pickup Datetime') }}</label>
            <input type="datetime-local" name="planlanan_alis_at" value="{{ $pickup?->format('Y-m-d\TH:i') ?? old('planlanan_alis_at') }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none">
        </div>
        <div>
            <label class="text-xs text-default block mb-1">{{ __('Return Datetime') }}</label>
            <input type="datetime-local" name="planlanan_teslim_at" value="{{ $return?->format('Y-m-d\TH:i') ?? old('planlanan_teslim_at') }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none">
        </div>
        <div>
            <button class="btn-sm bg-primary text-white w-full"><i class="ph ph-magnifying-glass"></i> {{ __('Search Available Vehicles') }}</button>
        </div>
    </div>
</form>

@if ($pickup && $return && $searchError === null)
    @if ($usageRule->icerik_html)
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
            <div class="font-semibold mb-2">{{ __('Usage Rules') }}</div>
            <div class="prose prose-sm max-w-none">{!! $usageRule->icerik_html !!}</div>
        </div>
    @endif

    @if ($availableVehicles->isEmpty())
        <div class="bg-warning-transparent text-warning border border-warning rounded-md p-4 text-center">{{ __('No available vehicles for the selected range.') }}</div>
    @else
        <form method="POST" action="{{ route('app.fleet.reservations.store') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @csrf
            <input type="hidden" name="planlanan_alis_at" value="{{ $pickup->format('Y-m-d H:i:s') }}">
            <input type="hidden" name="planlanan_teslim_at" value="{{ $return->format('Y-m-d H:i:s') }}">

            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white border border-border-color rounded-md p-4">
                    <div class="font-semibold mb-3">{{ __('Vehicle') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($availableVehicles as $v)
                            <label class="border border-border-color rounded-md p-3 cursor-pointer hover:border-primary block">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="arac_id" value="{{ $v->id }}" required>
                                    <div>
                                        <div class="font-semibold">{{ $v->plaka }}</div>
                                        <div class="text-xs text-default">{{ $v->marka_model }} · {{ $v->guncel_km }} km</div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="bg-white border border-border-color rounded-md p-4">
                    <label class="text-xs text-default block mb-1">{{ __('Project') }}</label>
                    <select name="proje_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none">
                        <option value="">—</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->ad }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bg-white border border-border-color rounded-md p-4">
                    <label class="text-xs text-default block mb-2">{{ __('Additional Drivers') }} <span class="text-default">({{ __('optional') }})</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                        @foreach ($candidateDrivers as $u)
                            <label class="text-sm flex items-center gap-2">
                                <input type="checkbox" name="ek_soforler[]" value="{{ $u->id }}">
                                <span>{{ $u->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <aside class="bg-white border border-border-color rounded-md p-4 self-start sticky top-4">
                <div class="font-semibold mb-2">{{ __('Summary') }}</div>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-default">{{ __('Pickup Datetime') }}</dt><dd>{{ $pickup->format('d.m.Y H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-default">{{ __('Return Datetime') }}</dt><dd>{{ $return->format('d.m.Y H:i') }}</dd></div>
                </dl>
                <button class="mt-4 w-full btn-sm bg-primary text-white">{{ __('Reserve') }}</button>
            </aside>
        </form>
    @endif
@endif
@endsection
