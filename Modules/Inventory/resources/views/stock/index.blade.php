@extends('app.layouts.app')

@section('title', __('Stock'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Stock') }}</h1>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Warehouse') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Location') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Lot') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reserved') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Available') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quants as $quant)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $quant->product->name }} <span class="text-default font-normal">({{ $quant->product->uom->name }})</span></td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $quant->location->warehouse?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $quant->location->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $quant->lot?->lot_number ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $quant->qty }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $quant->reserved_qty }}</td>
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ bcsub($quant->qty, $quant->reserved_qty, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No stock recorded yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
