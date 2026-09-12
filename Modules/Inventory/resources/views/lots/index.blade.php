@extends('app.layouts.app')

@section('title', __('Lots / Serial Numbers'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Lots / Serial Numbers') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Track lot numbers and expiry dates for lot- and serial-tracked products.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-lot-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Lot') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('lot')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror
@error('lot_number')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<form method="GET" action="{{ route('app.inventory.lots.index') }}" class="bg-white border border-border-color rounded-md p-4 mb-4 flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-40">
        <label class="text-xs text-default mb-1 block">{{ __('Product') }}</label>
        <select name="product_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">{{ __('— All') }}</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected($filterProductId === $product->id)>{{ $product->name }}</option>
            @endforeach
        </select>
    </div>
    <label class="inline-flex items-center gap-2 text-sm pb-2">
        <input type="checkbox" name="expired_only" value="1" @checked($filterExpiredOnly) class="rounded border-border-color">
        <span>{{ __('Show expired only') }}</span>
    </label>
    <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Filter') }}</button>
</form>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Lot / Serial') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Expiry') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('On Hand') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lots as $lot)
                    @php
                        $isExpired = $lot->expiry_date !== null && $lot->expiry_date->isPast();
                    @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title font-mono">{{ $lot->lot_number }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $lot->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm">
                            @if ($lot->expiry_date)
                                <span class="{{ $isExpired ? 'text-danger font-semibold' : 'text-default' }}">
                                    {{ $lot->expiry_date->format('d.m.Y') }}
                                    @if ($isExpired)
                                        <span class="text-[10px] bg-danger-transparent text-danger px-1 py-0.5 rounded ms-1">{{ __('Expired') }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-default">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $lotStocks[$lot->id] ?? '0' }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('app.inventory.lots.trace', $lot) }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Trace') }}">
                                    <i class="ph ph-graph text-xs"></i>
                                </a>
                                <button type="button" data-hs-overlay="#edit-lot-modal-{{ $lot->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.lots.destroy', $lot) }}" onsubmit="return confirm('{{ __('Delete this lot?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No lots yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-lot-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.lots.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Lot') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-lot-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }} <span class="text-danger">*</span></label>
                    <select name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Lot / Serial') }} <span class="text-danger">*</span></label>
                    <input type="text" name="lot_number" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Expiry Date') }}</label>
                    <input type="date" name="expiry_date" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-lot-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@foreach ($lots as $lot)
    <div id="edit-lot-modal-{{ $lot->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.inventory.lots.update', $lot) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                @method('PATCH')
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Edit Lot') }} — {{ $lot->lot_number }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-lot-modal-{{ $lot->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                </div>
                <div class="p-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Expiry Date') }}</label>
                    <input type="date" name="expiry_date" value="{{ $lot->expiry_date?->format('Y-m-d') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <p class="text-xs text-default mt-2 mb-0">{{ __('The lot number cannot be changed once created.') }}</p>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-lot-modal-{{ $lot->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
