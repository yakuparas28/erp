@extends('app.layouts.app')

@php
    $isIncoming = $direction === 'incoming';
    $pageTitle = $isIncoming ? __('Incoming Checks & Notes') : __('Outgoing Checks & Notes');
    $partnerLabel = $isIncoming ? __('From (Customer)') : __('To (Supplier)');

    $statusLabels = [
        'portfolio' => __('In Portfolio'),
        'endorsed' => __('Endorsed'),
        'sent_to_bank' => __('Sent to Bank'),
        'collected' => __('Collected'),
        'paid' => __('Paid'),
        'bounced' => __('Bounced'),
        'cancelled' => __('Cancelled'),
    ];

    $statusBadge = fn (string $s) => match ($s) {
        'portfolio' => 'bg-info-transparent text-info',
        'endorsed' => 'bg-warning-transparent text-warning',
        'sent_to_bank' => 'bg-warning-transparent text-warning',
        'collected', 'paid' => 'bg-success-transparent text-success',
        'bounced' => 'bg-danger-transparent text-danger',
        default => 'bg-light text-default',
    };
@endphp

@section('title', $pageTitle)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ $pageTitle }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ $isIncoming ? __('Checks and promissory notes received from customers.') : __('Checks and promissory notes issued to suppliers.') }}
        </p>
    </div>
    <button type="button" data-cn-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('New Instrument') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2 mb-4">
    @foreach ($statusLabels as $key => $label)
        @php $s = $statusCounts[$key] ?? null; @endphp
        <a href="{{ route(request()->route()->getName(), ['status' => $status === $key ? null : $key]) }}"
           class="bg-white border {{ $status === $key ? 'border-primary' : 'border-border-color' }} rounded-md p-3 text-center hover:bg-light cursor-pointer">
            <div class="text-[11px] text-default uppercase">{{ $label }}</div>
            <div class="text-lg font-bold text-gray-900">{{ $s?->c ?? 0 }}</div>
            <div class="text-[11px] text-default">{{ number_format((float) ($s?->total ?? 0), 2, ',', '.') }}</div>
        </a>
    @endforeach
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('No') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Type') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ $partnerLabel }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Bank / Drawer') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Maturity') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Amount') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $note)
                    @php
                        $days = $note->daysToMaturity();
                        $urgent = in_array($note->status, ['portfolio', 'sent_to_bank']) && $days <= 7 && $days >= 0;
                        $overdue = in_array($note->status, ['portfolio', 'sent_to_bank']) && $days < 0;
                    @endphp
                    <tr class="border-b border-border-color {{ $overdue ? 'bg-danger-transparent' : ($urgent ? 'bg-warning-transparent' : '') }}">
                        <td class="py-2 font-mono text-gray-900">{{ $note->instrument_no }}</td>
                        <td class="py-2 text-default text-[11px]">
                            {{ $note->instrument_type === 'check' ? __('Check') : __('Note') }}
                        </td>
                        <td class="py-2 text-gray-900">{{ $note->partner->name }}</td>
                        <td class="py-2 text-default">
                            {{ $note->drawee_bank_name ?: $note->drawer_name ?: '—' }}
                            @if ($note->drawee_branch)<div class="text-[11px]">{{ $note->drawee_branch }}</div>@endif
                        </td>
                        <td class="py-2 text-default">
                            {{ $note->maturity_date->format('d.m.Y') }}
                            @if ($overdue)
                                <span class="text-[11px] text-danger">({{ abs($days) }} {{ __('gün gecikmiş') }})</span>
                            @elseif ($urgent)
                                <span class="text-[11px] text-warning">({{ $days }} {{ __('gün kaldı') }})</span>
                            @endif
                        </td>
                        <td class="py-2 text-right font-semibold text-gray-900">{{ number_format((float) $note->amount, 2, ',', '.') }}</td>
                        <td class="py-2">
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $statusBadge($note->status) }}">
                                {{ $statusLabels[$note->status] ?? $note->status }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <a href="{{ route('app.accounting.checks-and-notes.show', $note) }}" class="text-primary text-[12px] hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-default">{{ __('No instruments found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::checks-and-notes._form-modal', [
    'direction' => $direction,
    'partners' => $partners,
    'currencies' => $currencies,
])
@endsection
