@extends('app.layouts.app')

@section('title', __('Exchange Rates'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Exchange Rates') }}</h1>
    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('app.accounting.exchange-rates.sync-tcmb') }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer" title="{{ __('Fetches today\'s USD/EUR rates from tcmb.gov.tr.') }}">
                <i class="ph ph-cloud-arrow-down"></i> {{ __('Fetch from TCMB') }}
            </button>
        </form>
        <button type="button" data-hs-overlay="#add-exchange-rate-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('New Exchange Rate') }}
        </button>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="bg-warning-transparent text-warning border border-warning rounded-md px-4 py-3 text-sm mb-4">{{ session('warning') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Currency') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Buy Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sell Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Source') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rate->currency->code }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->rate_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->buy_rate }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->sell_rate }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ $rate->source === 'tcmb' ? 'bg-info-transparent text-info' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded">
                                {{ __('exchange-rate-source.'.$rate->source) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No exchange rates yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::exchange-rates._form-modal', ['id' => 'add-exchange-rate-modal', 'action' => route('app.accounting.exchange-rates.store'), 'title' => __('New Exchange Rate'), 'currencies' => $currencies])
@endsection
