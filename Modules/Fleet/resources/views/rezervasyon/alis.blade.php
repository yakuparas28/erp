@extends('app.layouts.app')

@section('title', __('Confirm Pickup'))

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-xl font-bold mb-4">{{ __('Confirm Pickup') }} · #{{ str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT) }}</h1>

    @if ($errors->any())
        <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <dl class="text-sm space-y-1">
            <div class="flex justify-between"><dt class="text-default">{{ __('Vehicle') }}</dt><dd class="font-semibold">{{ $reservation->vehicle?->plaka }} — {{ $reservation->vehicle?->marka_model }}</dd></div>
            <div class="flex justify-between"><dt class="text-default">{{ __('Project') }}</dt><dd>{{ $reservation->project?->ad ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-default">{{ __('Current KM') }}</dt><dd>{{ number_format($reservation->vehicle?->guncel_km ?? 0) }}</dd></div>
        </dl>
    </div>

    <form method="POST" action="{{ route('app.fleet.reservations.pickup.store', $reservation) }}" class="bg-white border border-border-color rounded-md p-4">
        @csrf
        <label class="text-xs text-default block mb-1">{{ __('KM reading on the odometer') }}</label>
        <input type="number" name="okunan_km" min="0" required autofocus value="{{ $reservation->vehicle?->guncel_km }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none">
        <button class="mt-4 w-full btn-sm bg-primary text-white">{{ __('Confirm Pickup') }}</button>
    </form>
</div>
@endsection
