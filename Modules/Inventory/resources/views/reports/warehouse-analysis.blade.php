@extends('app.layouts.app')

@section('title', __('Warehouse Analysis'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Warehouse Analysis') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Move volume and current on-hand per warehouse.') }}</p>
    </div>
</div>

<div class="grid grid-cols-12 gap-4">
    @forelse ($stats as $row)
        <div class="col-span-12 lg:col-span-6">
            <div class="bg-white border border-border-color rounded-md">
                <div class="p-4 border-b border-border-color bg-light">
                    <h2 class="text-base font-bold text-title mb-0">{{ $row->warehouse->name }}</h2>
                    <p class="text-xs text-default mb-0">{{ $row->location_count }} {{ __('locations') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4 p-4">
                    <div>
                        <div class="text-xs text-default">{{ __('Moves') }}</div>
                        <div class="text-lg font-bold text-title">{{ $row->move_count }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-default">{{ __('On Hand') }}</div>
                        <div class="text-lg font-bold text-title">{{ $row->on_hand }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-default">{{ __('Inbound') }}</div>
                        <div class="text-lg font-bold text-success">{{ $row->inbound }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-default">{{ __('Outbound') }}</div>
                        <div class="text-lg font-bold text-warning">{{ $row->outbound }}</div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-12">
            <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No warehouses.') }}</div>
        </div>
    @endforelse
</div>
@endsection
