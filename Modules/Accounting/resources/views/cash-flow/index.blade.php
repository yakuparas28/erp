@extends('app.layouts.app')

@section('title', __('Cash Flow Dashboard'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Cash Flow Dashboard') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Live snapshot of your cash position and what is coming up.') }}</p>
    </div>
</div>

{{-- Bakiyeler --}}
<div class="mb-6">
    <h2 class="text-sm font-bold text-title mb-2 uppercase text-default">{{ __('Cash & Bank Balances') }}</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php $totalBalance = '0'; @endphp
        @forelse ($balances as $row)
            @php $totalBalance = bcadd($totalBalance, (string) $row['balance'], 4); @endphp
            <a href="{{ route('app.accounting.cash-bank-accounts.statement', $row['journal']) }}" class="bg-white border border-border-color rounded-md p-3 hover:bg-light hover:border-primary cursor-pointer block">
                <div class="flex items-center gap-1 text-[11px] text-default">
                    <span class="{{ $row['journal']->type === 'cash' ? 'text-info' : 'text-warning' }}">
                        <i class="ph ph-{{ $row['journal']->type === 'cash' ? 'money' : 'bank' }}"></i>
                    </span>
                    {{ $row['journal']->name }}
                </div>
                <div class="text-lg font-bold text-gray-900 mt-1">
                    {{ number_format((float) $row['balance'], 2, ',', '.') }}
                </div>
            </a>
        @empty
            <div class="col-span-full text-[12px] text-default">
                {{ __('No cash or bank accounts defined yet.') }}
                <a href="{{ route('app.accounting.cash-bank-accounts.index') }}" class="text-primary hover:underline">{{ __('Add one →') }}</a>
            </div>
        @endforelse
        @if (count($balances) > 0)
            <div class="bg-primary text-white rounded-md p-3">
                <div class="text-[11px] uppercase opacity-80">{{ __('Total') }}</div>
                <div class="text-lg font-bold mt-1">{{ number_format((float) $totalBalance, 2, ',', '.') }}</div>
            </div>
        @endif
    </div>
</div>

{{-- Projeksiyon --}}
<div class="mb-6">
    <h2 class="text-sm font-bold text-title mb-2 uppercase text-default">{{ __('30 / 60 / 90 Day Projection') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @foreach ($projection as $p)
            <div class="bg-white border border-border-color rounded-md p-4">
                <div class="text-[11px] uppercase text-default mb-2">{{ __('Next :days days', ['days' => $p['days']]) }}</div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-default">{{ __('Expected in') }}</span><span class="text-success font-semibold">+{{ number_format((float) $p['expected_in'], 2, ',', '.') }}</span></div>
                    <div class="flex justify-between"><span class="text-default">{{ __('Expected out') }}</span><span class="text-danger font-semibold">−{{ number_format((float) $p['expected_out'], 2, ',', '.') }}</span></div>
                    <div class="flex justify-between border-t border-border-color pt-1 mt-1"><span class="font-bold text-title">{{ __('Net') }}</span><span class="font-bold {{ bccomp($p['net'], '0', 4) >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format((float) $p['net'], 2, ',', '.') }}</span></div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Çek/Senet Bucket'ları --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-6">
    @php
        $bucketLabels = [
            'overdue' => __('Overdue'),
            '0_7' => __('0-7 days'),
            '8_30' => __('8-30 days'),
            '31_60' => __('31-60 days'),
            '61_90' => __('61-90 days'),
            '90_plus' => __('90+ days'),
        ];
    @endphp
    <div class="bg-white border border-border-color rounded-md p-4">
        <h3 class="text-sm font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-note text-success"></i> {{ __('Incoming Checks by Aging') }}</h3>
        <table class="w-full text-sm">
            @foreach ($bucketLabels as $key => $label)
                <tr class="border-b border-border-color">
                    <td class="py-1.5 text-default {{ $key === 'overdue' ? 'text-danger font-semibold' : '' }}">{{ $label }}</td>
                    <td class="py-1.5 text-right text-default">{{ $incomingBuckets[$key]['count'] }} {{ __('adet') }}</td>
                    <td class="py-1.5 text-right font-semibold text-gray-900">{{ number_format((float) $incomingBuckets[$key]['total'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="bg-white border border-border-color rounded-md p-4">
        <h3 class="text-sm font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-note-pencil text-danger"></i> {{ __('Outgoing Checks by Aging') }}</h3>
        <table class="w-full text-sm">
            @foreach ($bucketLabels as $key => $label)
                <tr class="border-b border-border-color">
                    <td class="py-1.5 text-default {{ $key === 'overdue' ? 'text-danger font-semibold' : '' }}">{{ $label }}</td>
                    <td class="py-1.5 text-right text-default">{{ $outgoingBuckets[$key]['count'] }} {{ __('adet') }}</td>
                    <td class="py-1.5 text-right font-semibold text-gray-900">{{ number_format((float) $outgoingBuckets[$key]['total'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

{{-- Vadesi yaklaşanlar --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-6">
    <div class="bg-white border border-border-color rounded-md p-4">
        <h3 class="text-sm font-bold text-title mb-3">{{ __('Urgent Incoming (next 7 days)') }}</h3>
        <table class="w-full text-sm">
            @forelse ($urgentIncomingChecks as $c)
                <tr class="border-b border-border-color">
                    <td class="py-1.5 text-gray-900">
                        <a href="{{ route('app.accounting.checks-and-notes.show', $c) }}" class="hover:underline">{{ $c->instrument_no }}</a>
                        <div class="text-[11px] text-default">{{ optional($c->partner)->name }}</div>
                    </td>
                    <td class="py-1.5 text-right text-default text-[12px]">{{ $c->maturity_date->format('d.m.Y') }}</td>
                    <td class="py-1.5 text-right font-semibold text-success">{{ number_format((float) $c->amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-center text-default text-[12px]">{{ __('Nothing due in 7 days.') }}</td></tr>
            @endforelse
        </table>
    </div>

    <div class="bg-white border border-border-color rounded-md p-4">
        <h3 class="text-sm font-bold text-title mb-3">{{ __('Urgent Outgoing (next 7 days)') }}</h3>
        <table class="w-full text-sm">
            @forelse ($urgentOutgoingChecks as $c)
                <tr class="border-b border-border-color">
                    <td class="py-1.5 text-gray-900">
                        <a href="{{ route('app.accounting.checks-and-notes.show', $c) }}" class="hover:underline">{{ $c->instrument_no }}</a>
                        <div class="text-[11px] text-default">{{ optional($c->partner)->name }}</div>
                    </td>
                    <td class="py-1.5 text-right text-default text-[12px]">{{ $c->maturity_date->format('d.m.Y') }}</td>
                    <td class="py-1.5 text-right font-semibold text-danger">{{ number_format((float) $c->amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-center text-default text-[12px]">{{ __('Nothing due in 7 days.') }}</td></tr>
            @endforelse
        </table>
    </div>
</div>

{{-- Bekleyen kart valörleri --}}
@if ($pendingCards->isNotEmpty())
    <div class="bg-white border border-border-color rounded-md p-4">
        <h3 class="text-sm font-bold text-title mb-3">{{ __('Pending Card Settlements') }}</h3>
        <table class="w-full text-sm">
            @foreach ($pendingCards as $card)
                <tr class="border-b border-border-color">
                    <td class="py-1.5 text-gray-900">{{ optional($card->partner)->name }} · <span class="text-default text-[11px]">{{ optional($card->terminal)->name }}</span></td>
                    <td class="py-1.5 text-right text-default text-[12px]">{{ $card->expected_settlement_date->format('d.m.Y') }}</td>
                    <td class="py-1.5 text-right font-semibold text-success">{{ number_format((float) $card->net_amount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@endsection
