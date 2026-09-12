@extends('app.layouts.app')

@section('title', __('Forecasted Report'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Forecasted Report') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Forecast = On-Hand + Confirmed Incoming − Confirmed Outgoing') }}</p>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('On Hand') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Incoming') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Outgoing') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Forecast') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-border-color">
                        <td class="py-2 px-3 text-sm font-semibold text-title">{{ $row->product->name }}</td>
                        <td class="py-2 px-3 text-sm text-default text-right">{{ $row->on_hand }}</td>
                        <td class="py-2 px-3 text-sm text-success text-right">+{{ $row->incoming }}</td>
                        <td class="py-2 px-3 text-sm text-warning text-right">−{{ $row->outgoing }}</td>
                        <td class="py-2 px-3 text-sm font-semibold text-right {{ (float) $row->forecast < 0 ? 'text-danger' : 'text-title' }}">{{ $row->forecast }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No data.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
