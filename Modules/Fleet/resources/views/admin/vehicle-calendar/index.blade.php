@extends('app.layouts.app')

@section('title', __('Vehicle Calendar'))

@section('content')
<h1 class="text-gray-900 text-xl font-bold mb-4">{{ __('Vehicle Calendar') }}</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
    @foreach ($vehicles as $v)
        <a href="{{ route('app.fleet.vehicle-calendar.show', $v) }}" class="block bg-white border border-border-color rounded-md p-4 hover:border-primary">
            <div class="flex items-center justify-between mb-2">
                <div class="font-semibold">{{ $v->plaka }}</div>
                <span class="text-xs px-2 py-0.5 rounded {{ $v->durum === 'Blokeli_Bakimda' ? 'bg-danger-transparent text-danger' : 'bg-gray-100 text-default' }}">{{ __('vehicle-status.'.$v->durum) }}</span>
            </div>
            <div class="text-xs text-default mb-2">{{ $v->marka_model }}</div>
            @if ($v->calendarBlocks->isNotEmpty())
                <ul class="text-xs space-y-1">
                    @foreach ($v->calendarBlocks as $b)
                        @php $cls = ['bakim' => 'text-warning', 'muayene' => 'text-info', 'bloke' => 'text-danger'][$b->block_type] ?? ''; @endphp
                        <li class="{{ $cls }}">{{ __('block-type.'.$b->block_type) }}: {{ $b->start_date->format('d.m') }} → {{ $b->end_date->format('d.m') }}</li>
                    @endforeach
                </ul>
            @else
                <div class="text-xs text-success">{{ __('No upcoming blocks') }}</div>
            @endif
        </a>
    @endforeach
</div>
@endsection
