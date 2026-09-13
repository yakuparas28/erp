@extends('app.layouts.app')

@section('title', __('Submit Delivery'))

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold mb-4">{{ __('Submit Delivery') }} · #{{ str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT) }}</h1>

    @if ($errors->any())
        <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('app.fleet.reservations.delivery.store', $reservation) }}" enctype="multipart/form-data" class="bg-white border border-border-color rounded-md p-4 space-y-4" x-data="{ beyan: '1' }">
        @csrf
        <div>
            <label class="text-xs text-default block mb-1">{{ __('Return KM') }} <span class="text-default">({{ __('Pickup KM') }}: {{ number_format($reservation->alis_km) }})</span></label>
            <input type="number" name="teslim_km" min="{{ $reservation->alis_km }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none">
        </div>
        <div>
            <label class="text-xs text-default block mb-1">{{ __('Vehicle Condition Statement') }}</label>
            <div class="flex gap-4">
                <label class="text-sm flex items-center gap-2"><input type="radio" name="teslim_beyani" value="1" x-model="beyan" checked> {{ __('No issues') }}</label>
                <label class="text-sm flex items-center gap-2"><input type="radio" name="teslim_beyani" value="0" x-model="beyan"> {{ __('Reporting an issue') }}</label>
            </div>
        </div>
        <div x-show="beyan === '0'" x-cloak>
            <label class="text-xs text-default block mb-1">{{ __('Issue Description') }}</label>
            <textarea name="teslim_ariza_aciklamasi" rows="3" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none"></textarea>
        </div>
        <div>
            <div class="text-xs text-default mb-2">{{ __('Required photos (front, back, right, left, odometer)') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                @foreach (['on' => __('Front'), 'arka' => __('Back'), 'sag' => __('Right'), 'sol' => __('Left'), 'km' => __('Odometer')] as $slot => $label)
                    <label class="block border border-border-color rounded-md p-2 text-center text-xs">
                        <div class="mb-2 text-default">{{ $label }}</div>
                        <input type="file" name="teslim_foto_{{ $slot }}" accept="image/*" capture="environment" required class="text-xs">
                    </label>
                @endforeach
            </div>
        </div>
        <button class="w-full btn-sm bg-primary text-white">{{ __('Submit Delivery') }}</button>
    </form>
</div>
@endsection
