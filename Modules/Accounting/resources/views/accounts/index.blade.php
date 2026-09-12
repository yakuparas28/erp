@extends('app.layouts.app')

@section('title', __('Chart of Accounts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Chart of Accounts') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('The three-digit main accounts are set by the Turkish Uniform Chart of Accounts and are read-only. Add sub-accounts (e.g. 100.01) under them.') }}</p>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

@error('account')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror
@error('code')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

@php
    $classLabels = [
        '1' => __('Current Assets'),
        '2' => __('Non-Current Assets'),
        '3' => __('Short-Term Liabilities'),
        '4' => __('Long-Term Liabilities'),
        '5' => __('Equity'),
        '6' => __('Income Statement Accounts'),
        '7' => __('Cost Accounts'),
    ];
    $typeColors = [
        'asset' => 'bg-success-transparent text-success',
        'liability' => 'bg-danger-transparent text-danger',
        'equity' => 'bg-info-transparent text-info',
        'income' => 'bg-primary-transparent text-primary',
        'expense' => 'bg-warning-transparent text-warning',
    ];
    $grouped = $systemAccounts->groupBy(fn ($a) => substr($a->code, 0, 1));
@endphp

<div class="space-y-4">
    @foreach ($grouped as $classKey => $classAccounts)
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color bg-light">
                <h2 class="text-base font-bold text-title mb-0">{{ $classKey }}. {{ $classLabels[$classKey] ?? __('Other') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900 w-32">{{ __('Code') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900 w-32">{{ __('Type') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900 w-40">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classAccounts as $systemAccount)
                            <tr class="border-b border-border-color bg-light/40">
                                <td class="py-2 px-3 text-sm font-bold text-title">{{ $systemAccount->code }}</td>
                                <td class="py-2 px-3 text-sm text-title">{{ $systemAccount->name }}</td>
                                <td class="py-2 px-3">
                                    <span class="text-[11px] {{ $typeColors[$systemAccount->type] ?? 'bg-default-transparent text-default' }} px-2 py-0.5 rounded">
                                        {{ __('account-type.'.$systemAccount->type) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">
                                    <button type="button" data-hs-overlay="#add-sub-account-{{ $systemAccount->id }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-1 hover:bg-light cursor-pointer" title="{{ __('Add sub-account') }}">
                                        <i class="ph ph-plus text-xs"></i> {{ __('Sub-account') }}
                                    </button>
                                </td>
                            </tr>
                            @foreach ($accounts->where('parent_id', $systemAccount->id)->sortBy('code') as $sub)
                                <tr class="border-b border-border-color">
                                    <td class="py-2 px-3 text-sm text-default ps-8">↳ {{ $sub->code }}</td>
                                    <td class="py-2 px-3 text-sm text-default">{{ $sub->name }}</td>
                                    <td class="py-2 px-3 text-xs text-default">{{ __('account-type.'.$sub->type) }}</td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            <button type="button" data-hs-overlay="#edit-sub-account-{{ $sub->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                                <i class="ph ph-pencil-simple text-xs"></i>
                                            </button>
                                            <form method="POST" action="{{ route('app.accounting.accounts.destroy', $sub) }}" onsubmit="return confirm('{{ __('Delete this account?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                                    <i class="ph ph-trash text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>

@foreach ($systemAccounts as $systemAccount)
    @include('accounting::accounts._sub-modal', ['parent' => $systemAccount])
@endforeach

@foreach ($accounts->where('is_system', false) as $sub)
    @include('accounting::accounts._edit-modal', ['account' => $sub])
@endforeach
@endsection
