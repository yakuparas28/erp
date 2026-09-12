@extends('app.layouts.app')

@section('title', __('Scrap'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Scrap') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Move damaged or expired products to the Scrap virtual location. This is an immutable stock move.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-scrap-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Scrap') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('scrap')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Lot') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reason') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('By') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($scraps as $scrap)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->scrapped_at->format('d.m.Y H:i') }}</td>
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $scrap->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->qty }} {{ $scrap->uom->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->sourceLocation->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->lot?->lot_number ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->reason ?: '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $scrap->doneBy->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No scrap records yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-scrap-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.scraps.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Scrap') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-scrap-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }} <span class="text-danger">*</span></label>
                    <select name="product_id" id="scrap-product-select" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-uom-id="{{ $product->uom_id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Source Location') }} <span class="text-danger">*</span></label>
                    <select name="source_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }} <span class="text-danger">*</span></label>
                    <input type="number" name="qty" required step="0.0001" min="0.0001" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }} <span class="text-danger">*</span></label>
                    <select name="uom_id" id="scrap-uom-select" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($products->pluck('uom')->unique('id') as $uom)
                            <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Lot / Serial') }}</label>
                    <select name="lot_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">{{ __('— None') }}</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Reason') }}</label>
                    <textarea name="reason" rows="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-scrap-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Scrap') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var productSelect = document.getElementById('scrap-product-select');
        var uomSelect = document.getElementById('scrap-uom-select');
        if (!productSelect || !uomSelect) return;
        productSelect.addEventListener('change', function () {
            var selected = productSelect.options[productSelect.selectedIndex];
            var uomId = selected ? selected.getAttribute('data-uom-id') : null;
            if (uomId) uomSelect.value = uomId;
        });
    })();
</script>
@endsection
