@extends('app.layouts.app')

@section('title', __('Inventory Valuation'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Inventory Valuation') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Cost-basis of on-hand stock derived from valuation layers.') }}</p>
    </div>
    <div class="text-right">
        <div class="text-xs text-default">{{ __('Total Value') }}</div>
        <div class="text-xl font-bold text-title">{{ $totalValue }}</div>
    </div>
</div>

<div class="space-y-3">
    @forelse ($grouped as $categoryName => $rows)
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-3 border-b border-border-color bg-light">
                <h2 class="text-sm font-bold text-title mb-0">{{ $categoryName }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Unit Cost') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-border-color">
                                <td class="py-2 px-3 text-sm font-semibold text-title">{{ $row->product->name }}</td>
                                <td class="py-2 px-3 text-sm text-default text-right">{{ $row->qty }}</td>
                                <td class="py-2 px-3 text-sm text-default text-right">{{ $row->unit_cost }}</td>
                                <td class="py-2 px-3 text-sm font-semibold text-title text-right">{{ $row->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No valuation layers yet.') }}</div>
    @endforelse
</div>
@endsection
