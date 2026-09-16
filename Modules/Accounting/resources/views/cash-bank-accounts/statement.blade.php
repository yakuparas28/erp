@extends('app.layouts.app')

@section('title', __('Account Statement').' — '.$journal->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">
            {{ $journal->name }}
            <span class="text-[11px] px-2 py-0.5 rounded {{ $journal->type === 'cash' ? 'bg-info-transparent text-info' : 'bg-warning-transparent text-warning' }} ms-1">
                {{ $journal->type === 'cash' ? __('Cash') : __('Bank') }}
            </span>
        </h1>
        <p class="text-[12px] text-default mb-0">
            {{ optional($journal->currency)->code ?? 'TRY' }}
            @if ($journal->chartOfAccount) · {{ __('COA') }}: <span class="font-mono">{{ $journal->chartOfAccount->code }}</span> @endif
            @if ($journal->iban) · <span class="font-mono">{{ $journal->iban }}</span> @endif
        </p>
    </div>
    <a href="{{ route('app.accounting.cash-bank-accounts.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2">
        <i class="ph ph-arrow-left"></i> {{ __('Back') }}
    </a>
</div>

<form method="GET" action="{{ route('app.accounting.cash-bank-accounts.statement', $journal) }}" class="bg-white border border-border-color rounded-md p-3 mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="text-[11px] uppercase text-default block mb-1">{{ __('From') }}</label>
        <input type="date" name="from" value="{{ $from }}" class="px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div>
        <label class="text-[11px] uppercase text-default block mb-1">{{ __('To') }}</label>
        <input type="date" name="to" value="{{ $to }}" class="px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-magnifying-glass"></i> {{ __('Apply') }}
    </button>
    <div class="flex items-center gap-1 text-[11px]">
        <a href="{{ route('app.accounting.cash-bank-accounts.statement', ['journal' => $journal, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}" class="text-primary hover:underline">{{ __('This month') }}</a>
        ·
        <a href="{{ route('app.accounting.cash-bank-accounts.statement', ['journal' => $journal, 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]) }}" class="text-primary hover:underline">{{ __('This year') }}</a>
        ·
        <a href="{{ route('app.accounting.cash-bank-accounts.statement', ['journal' => $journal, 'from' => '', 'to' => '']) }}" class="text-primary hover:underline">{{ __('All time') }}</a>
    </div>
</form>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Opening') }}</div>
        <div class="text-lg font-bold text-gray-900">{{ number_format((float) $statement['opening'], 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Debit') }} <span class="text-[10px]">({{ __('inflow') }})</span></div>
        <div class="text-lg font-bold text-success">+{{ number_format((float) $statement['total_debit'], 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Credit') }} <span class="text-[10px]">({{ __('outflow') }})</span></div>
        <div class="text-lg font-bold text-danger">−{{ number_format((float) $statement['total_credit'], 2, ',', '.') }}</div>
    </div>
    <div class="bg-primary text-white rounded-md p-3">
        <div class="text-[11px] uppercase opacity-80">{{ __('Closing') }}</div>
        <div class="text-lg font-bold">{{ number_format((float) $statement['closing'], 2, ',', '.') }}</div>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Description') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Debit') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Credit') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-border-color bg-light">
                    <td colspan="4" class="py-2 text-default text-[11px] uppercase font-medium">{{ __('Opening Balance') }}</td>
                    <td class="py-2 text-right font-bold">{{ number_format((float) $statement['opening'], 2, ',', '.') }}</td>
                </tr>
                @forelse ($statement['rows'] as $row)
                    <tr class="border-b border-border-color">
                        <td class="py-2 text-default text-[12px]">{{ $row['date'] }}</td>
                        <td class="py-2 text-gray-900">
                            {{ $row['description'] }}
                            @if ($row['ref'])
                                <a href="{{ route('app.accounting.journal-entries.show', $row['entry_id']) }}" class="text-primary text-[11px] hover:underline">→</a>
                            @endif
                        </td>
                        <td class="py-2 text-right text-success">
                            {{ bccomp($row['debit'], '0', 4) > 0 ? '+'.number_format((float) $row['debit'], 2, ',', '.') : '' }}
                        </td>
                        <td class="py-2 text-right text-danger">
                            {{ bccomp($row['credit'], '0', 4) > 0 ? '−'.number_format((float) $row['credit'], 2, ',', '.') : '' }}
                        </td>
                        <td class="py-2 text-right font-semibold {{ bccomp($row['running'], '0', 4) < 0 ? 'text-danger' : 'text-gray-900' }}">
                            {{ number_format((float) $row['running'], 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-default">{{ __('No transactions in the selected date range.') }}</td></tr>
                @endforelse
                <tr class="border-t-2 border-border-color bg-light">
                    <td colspan="4" class="py-2 text-default text-[11px] uppercase font-medium">{{ __('Closing Balance') }}</td>
                    <td class="py-2 text-right font-bold {{ bccomp($statement['closing'], '0', 4) < 0 ? 'text-danger' : 'text-primary' }}">
                        {{ number_format((float) $statement['closing'], 2, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
