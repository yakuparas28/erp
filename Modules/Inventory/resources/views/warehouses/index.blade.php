@extends('app.layouts.app')

@section('title', __('Warehouses'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Warehouses') }}</h1>
    <button type="button" data-hs-overlay="#add-warehouse-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Warehouse') }}
    </button>
</div>

<div class="grid grid-cols-12 gap-4">
    @forelse ($warehouses as $warehouse)
        <div class="col-span-12 lg:col-span-6">
            <div class="bg-white border border-border-color rounded-md p-4 h-full">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-title mb-0">{{ $warehouse->name }}</h2>
                    <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ $warehouse->code }}</span>
                </div>
                <ul class="space-y-1 mb-3">
                    @forelse ($warehouse->locations as $location)
                        <li class="text-sm text-default flex items-center gap-2">
                            <i class="ph ph-map-pin"></i> {{ $location->name }}
                            @if ($location->counting_lock)
                                <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Locked (counting)') }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-default">{{ __('No locations yet.') }}</li>
                    @endforelse
                </ul>
                <button type="button" data-hs-overlay="#add-location-modal-{{ $warehouse->id }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
                    <i class="ph ph-plus"></i> {{ __('Add Location') }}
                </button>
            </div>
        </div>
    @empty
        <div class="col-span-12 bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No warehouses yet.') }}</div>
    @endforelse
</div>

<div id="add-warehouse-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.warehouses.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Warehouse') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-warehouse-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-8">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-warehouse-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@foreach ($warehouses as $warehouse)
    <div id="add-location-modal-{{ $warehouse->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.inventory.locations.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Add Location') }} — {{ $warehouse->name }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-location-modal-{{ $warehouse->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Parent Location') }}</label>
                        <select name="parent_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">—</option>
                            @foreach ($warehouse->locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-location-modal-{{ $warehouse->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
