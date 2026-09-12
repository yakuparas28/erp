@extends('app.layouts.app')

@section('title', $currency->code . ' — ' . $currency->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $currency->code }} — {{ $currency->name }}</h1>
        <div class="flex items-center gap-2">
            <span class="text-sm text-default">{{ __('Symbol') }}: <strong>{{ $currency->symbol ?: '—' }}</strong></span>
            <span class="text-sm text-default">|</span>
            <span class="text-sm text-default">{{ __('Position') }}: <strong>{{ __($currency->position === 'before' ? 'Before amount' : 'After amount') }}</strong></span>
            <span class="text-sm text-default">|</span>
            <span class="text-sm text-default">{{ __('Rounding') }}: <strong>{{ $currency->rounding }}</strong> ({{ $currency->decimal_places }} {{ __('decimals') }})</span>
        </div>
    </div>
    <a href="{{ route('app.accounting.currencies.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="p-4 border-b border-border-color">
        <h2 class="text-base font-bold text-title mb-0">{{ __('Rate History') }}</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Buy Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sell Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Inverse (1 TRY)') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Source') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rate->rate_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->buy_rate }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->sell_rate }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rate->inverse_buy_rate }}</td>
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
@endsection
