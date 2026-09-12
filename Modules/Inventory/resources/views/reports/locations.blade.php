@extends('app.layouts.app')

@section('title', __('Locations Report'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Locations Report') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Where each product is currently held, grouped by location.') }}</p>
    </div>
</div>

<div class="space-y-3">
    @forelse ($rows as $row)
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-3 border-b border-border-color bg-light flex items-center justify-between">
                <h2 class="text-sm font-bold text-title mb-0">{{ $row->location->name }} <span class="text-xs font-normal text-default">({{ __('location-type.'.$row->location->type) }})</span></h2>
                <span class="text-xs text-default">{{ __('Total') }}: <strong>{{ $row->total_qty }}</strong></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($row->products as $item)
                            <tr class="border-b border-border-color">
                                <td class="py-2 px-3 text-sm font-semibold text-title">{{ $item->product?->name ?? '—' }}</td>
                                <td class="py-2 px-3 text-sm text-default text-right">{{ $item->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No stock on any location.') }}</div>
    @endforelse
</div>
@endsection
