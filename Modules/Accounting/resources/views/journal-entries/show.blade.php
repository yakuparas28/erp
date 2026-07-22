@extends('app.layouts.app')

@section('title', __('Journal Entry'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $entry->entry_date->format('d.m.Y') }} — {{ $entry->journal->name }}</h1>
        @include('accounting::journal-entries._status-badge', ['status' => $entry->status])
    </div>
    <a href="{{ route('app.accounting.journal-entries.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Account Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Account Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Debit') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entry->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->account->code }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->account->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ number_format((float) $line->debit, 4) }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ number_format((float) $line->credit, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-default">{{ __('No lines for this entry.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t border-border-color">
                    <td colspan="2" class="py-2.5 px-3 text-sm font-semibold text-title">{{ __('Total') }}</td>
                    <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ number_format((float) $entry->lines->sum('debit'), 4) }}</td>
                    <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ number_format((float) $entry->lines->sum('credit'), 4) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<p class="text-[11px] text-default mt-2 mb-0">{{ __('Debit and credit totals must always be equal — this is the visual proof of double-entry balance.') }}</p>
@endsection
