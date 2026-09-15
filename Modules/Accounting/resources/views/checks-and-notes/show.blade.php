@extends('app.layouts.app')

@php
    $statusLabels = [
        'portfolio' => __('In Portfolio'),
        'endorsed' => __('Endorsed'),
        'sent_to_bank' => __('Sent to Bank'),
        'collected' => __('Collected'),
        'paid' => __('Paid'),
        'bounced' => __('Bounced'),
        'cancelled' => __('Cancelled'),
    ];
    $isIncoming = $note->isIncoming();
    $canAct = $note->status === 'portfolio';
    $canCollect = in_array($note->status, ['portfolio', 'sent_to_bank']);
@endphp

@section('title', ($isIncoming ? __('Incoming') : __('Outgoing')).' — '.$note->instrument_no)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">
            {{ $note->instrument_type === 'check' ? __('Check') : __('Promissory Note') }}
            — {{ $note->instrument_no }}
        </h1>
        <span class="text-[11px] px-2 py-0.5 rounded bg-info-transparent text-info">{{ $statusLabels[$note->status] }}</span>
        <span class="text-sm text-default ms-2">{{ $note->partner->name }}</span>
    </div>
    <a href="{{ route($isIncoming ? 'app.accounting.incoming-checks.index' : 'app.accounting.outgoing-checks.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2">
        <i class="ph ph-arrow-left"></i> {{ __('Back') }}
    </a>
</div>

@error('transition')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror
@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Amount') }}</div>
        <div class="text-xl font-bold text-gray-900">{{ number_format((float) $note->amount, 2, ',', '.') }} {{ optional($note->currency)->code ?? 'TRY' }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Issue Date') }}</div>
        <div class="text-gray-900 font-medium">{{ $note->issue_date->format('d.m.Y') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Maturity Date') }}</div>
        <div class="text-gray-900 font-medium">{{ $note->maturity_date->format('d.m.Y') }}</div>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <h2 class="text-base font-bold text-title mb-3">{{ __('Details') }}</h2>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
        <div><dt class="text-default inline">{{ __('Drawer / Debtor') }}:</dt> <dd class="inline text-gray-900">{{ $note->drawer_name ?: '—' }}</dd></div>
        <div><dt class="text-default inline">{{ __('Bank') }}:</dt> <dd class="inline text-gray-900">{{ $note->drawee_bank_name ?: '—' }} {{ $note->drawee_branch ? '· '.$note->drawee_branch : '' }}</dd></div>
        @if ($note->endorsedToPartner)
            <div><dt class="text-default inline">{{ __('Endorsed To') }}:</dt> <dd class="inline text-gray-900">{{ $note->endorsedToPartner->name }}</dd></div>
        @endif
        @if ($note->collectionBankJournal)
            <div><dt class="text-default inline">{{ __('Bank / Cash') }}:</dt> <dd class="inline text-gray-900">{{ $note->collectionBankJournal->name }}</dd></div>
        @endif
        @if ($note->status_changed_at)
            <div><dt class="text-default inline">{{ __('Status Changed') }}:</dt> <dd class="inline text-gray-900">{{ $note->status_changed_at->format('d.m.Y') }}</dd></div>
        @endif
        @if ($note->notes)
            <div class="md:col-span-2 pt-2 border-t border-border-color mt-2">
                <dt class="text-default text-[11px] uppercase mb-1">{{ __('Notes') }}</dt>
                <dd class="text-gray-900 whitespace-pre-wrap">{{ $note->notes }}</dd>
            </div>
        @endif
    </dl>
</div>

@if ($isIncoming)
    @if ($canAct || $canCollect)
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-arrows-clockwise"></i> {{ __('Actions') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if ($canAct)
                    <form method="POST" action="{{ route('app.accounting.checks-and-notes.endorse', $note) }}" class="border border-border-color rounded-md p-3 space-y-2">
                        @csrf
                        <div class="font-semibold text-sm text-title">{{ __('Endorse to Supplier') }}</div>
                        <select name="endorsed_to_partner_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn-sm bg-warning text-white border border-warning hover:bg-warning/90 cursor-pointer">{{ __('Endorse') }}</button>
                    </form>

                    <form method="POST" action="{{ route('app.accounting.checks-and-notes.send-to-bank', $note) }}" class="border border-border-color rounded-md p-3 space-y-2">
                        @csrf
                        <div class="font-semibold text-sm text-title">{{ __('Send to Bank (Transit)') }}</div>
                        <select name="bank_journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($bankJournals as $j)<option value="{{ $j->id }}">{{ $j->name }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn-sm bg-info text-white border border-info hover:bg-info/90 cursor-pointer">{{ __('Send to Bank') }}</button>
                    </form>
                @endif

                @if ($canCollect)
                    <form method="POST" action="{{ route('app.accounting.checks-and-notes.collect', $note) }}" class="border border-border-color rounded-md p-3 space-y-2">
                        @csrf
                        <div class="font-semibold text-sm text-title">{{ __('Mark Collected') }}</div>
                        <select name="journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <optgroup label="{{ __('Bank') }}">
                                @foreach ($bankJournals as $j)<option value="{{ $j->id }}">{{ $j->name }}</option>@endforeach
                            </optgroup>
                            <optgroup label="{{ __('Cash') }}">
                                @foreach ($cashJournals as $j)<option value="{{ $j->id }}">{{ $j->name }}</option>@endforeach
                            </optgroup>
                        </select>
                        <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer">{{ __('Collect') }}</button>
                    </form>

                    <form method="POST" action="{{ route('app.accounting.checks-and-notes.bounce', $note) }}" class="border border-border-color rounded-md p-3 space-y-2"
                          onsubmit="return confirm('{{ __('Mark this instrument bounced?') }}')">
                        @csrf
                        <div class="font-semibold text-sm text-title">{{ __('Mark Bounced') }}</div>
                        <p class="text-[12px] text-default">{{ __('Returns the amount to the customer receivables account.') }}</p>
                        <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Bounce') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@else
    @if ($canAct)
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-arrows-clockwise"></i> {{ __('Actions') }}</h2>
            <form method="POST" action="{{ route('app.accounting.checks-and-notes.pay', $note) }}" class="border border-border-color rounded-md p-3 space-y-2 max-w-md">
                @csrf
                <div class="font-semibold text-sm text-title">{{ __('Mark Paid (from a bank/cash account)') }}</div>
                <select name="journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <optgroup label="{{ __('Bank') }}">
                        @foreach ($bankJournals as $j)<option value="{{ $j->id }}">{{ $j->name }}</option>@endforeach
                    </optgroup>
                    <optgroup label="{{ __('Cash') }}">
                        @foreach ($cashJournals as $j)<option value="{{ $j->id }}">{{ $j->name }}</option>@endforeach
                    </optgroup>
                </select>
                <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer">{{ __('Mark Paid') }}</button>
            </form>
        </div>
    @endif
@endif
@endsection
