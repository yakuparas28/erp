@extends('app.layouts.app')

@section('title', __('Sales Orders'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Sales Orders') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Confirmed / delivered orders. Create new orders via the') }} <a href="{{ route('app.sales.quotations.index') }}" class="text-primary hover:underline">{{ __('Quotations') }}</a> {{ __('flow.') }}</p>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Line Count') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created By') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $order->partner->name }}</td>
                        <td class="py-2.5 px-3">
                            @include('sales::orders._status-badge', ['status' => $order->status])
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $order->lines->count() }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $order->creator->name }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.sales.orders.show', $order) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No sales orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
