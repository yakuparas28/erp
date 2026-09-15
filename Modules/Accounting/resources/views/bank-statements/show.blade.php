@extends('app.layouts.app')

@section('title', $statement->original_filename)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $statement->original_filename }}</h1>
        <p class="text-[12px] text-default mb-0">
            {{ optional($statement->bankJournal)->name }} ·
            {{ optional($statement->period_start)->format('d.m.Y') }} → {{ optional($statement->period_end)->format('d.m.Y') }}
        </p>
    </div>
    <a href="{{ route('app.accounting.bank-statements.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2">
        <i class="ph ph-arrow-left"></i> {{ __('Back') }}
    </a>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Opening') }}</div>
        <div class="text-title font-semibold">{{ number_format((float) $statement->opening_balance, 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Closing') }}</div>
        <div class="text-title font-semibold">{{ number_format((float) $statement->closing_balance, 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Total Lines') }}</div>
        <div class="text-title font-semibold">{{ $statement->total_lines }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Matched') }}</div>
        <div class="text-title font-semibold">{{ $statement->matched_lines }} <span class="text-[12px] text-default">(%{{ $statement->matchPct() }})</span></div>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('match')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Description') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Debit') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Credit') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr class="border-b border-border-color {{ $line->status === 'unmatched' ? 'bg-warning-transparent' : '' }}">
                        <td class="py-2 text-default text-[12px]">{{ $line->transaction_date->format('d.m.Y') }}</td>
                        <td class="py-2 text-gray-900 text-[12px]">{{ $line->description }}</td>
                        <td class="py-2 text-right text-danger text-[12px]">
                            {{ bccomp((string) $line->debit, '0', 4) > 0 ? number_format((float) $line->debit, 2, ',', '.') : '' }}
                        </td>
                        <td class="py-2 text-right text-success text-[12px]">
                            {{ bccomp((string) $line->credit, '0', 4) > 0 ? number_format((float) $line->credit, 2, ',', '.') : '' }}
                        </td>
                        <td class="py-2">
                            @php
                                $badges = [
                                    'unmatched' => ['bg-warning-transparent text-warning', __('Unmatched')],
                                    'auto_matched' => ['bg-success-transparent text-success', __('Auto Matched')],
                                    'manual_matched' => ['bg-info-transparent text-info', __('Manual Match')],
                                    'ignored' => ['bg-light text-default', __('Ignored')],
                                ];
                                $b = $badges[$line->status] ?? ['bg-light text-default', $line->status];
                            @endphp
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $b[0] }}">{{ $b[1] }}</span>
                        </td>
                        <td class="py-2 text-right">
                            @if ($line->status === 'unmatched')
                                <details class="inline-block relative">
                                    <summary class="text-primary text-[12px] hover:underline cursor-pointer list-none">{{ __('Match') }}</summary>
                                    <div class="absolute right-0 mt-1 bg-white border border-border-color rounded-md shadow-lg p-3 z-10" style="width:280px;">
                                        <form method="POST" action="{{ route('app.accounting.bank-statements.lines.match', $line) }}" class="space-y-2">
                                            @csrf
                                            <input type="hidden" name="matched_type" value="payment">
                                            <label class="text-[11px] text-default block">{{ __('Match to payment') }}</label>
                                            <select name="matched_id" required class="w-full px-2 py-1 text-[12px] border border-border-color rounded-md bg-white">
                                                @foreach ($openPayments as $p)
                                                    <option value="{{ $p->id }}">{{ optional($p->partner)->name }} · {{ $p->amount }} · {{ $p->payment_date->format('d.m.Y') }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn-sm bg-primary text-white text-[11px] w-full">{{ __('Save Match') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('app.accounting.bank-statements.lines.ignore', $line) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="text-danger text-[11px] hover:underline">{{ __('Ignore this line') }}</button>
                                        </form>
                                    </div>
                                </details>
                            @else
                                <form method="POST" action="{{ route('app.accounting.bank-statements.lines.unmatch', $line) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-default text-[12px] hover:underline">{{ __('Unmatch') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
