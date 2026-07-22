@extends('app.layouts.app')

@section('title', __('Journal Entries'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Journal Entries') }}</h1>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Journal') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reference Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Total Amount') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $entry->entry_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $entry->journal->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $entry->reference_type }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ number_format((float) $entry->lines->sum('debit'), 4) }}</td>
                        <td class="py-2.5 px-3">
                            @include('accounting::journal-entries._status-badge', ['status' => $entry->status])
                        </td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.accounting.journal-entries.show', $entry) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No journal entries yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
