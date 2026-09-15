@extends('app.layouts.app')

@section('title', __('My Expenses'))

@section('content')
@php
    $statusBadge = fn ($s) => [
        'draft' => 'bg-light text-default',
        'submitted' => 'bg-warning-transparent text-warning',
        'approved' => 'bg-info-transparent text-info',
        'refused' => 'bg-danger-transparent text-danger',
        'posted' => 'bg-success-transparent text-success',
        'paid' => 'bg-success text-white',
    ][$s] ?? 'bg-light text-default';
    $totals = [
        'to_submit' => $expenses->where('status', 'draft')->sum('total_amount'),
        'waiting' => $expenses->where('status', 'submitted')->sum('total_amount'),
        'approved' => $expenses->whereIn('status', ['approved', 'posted'])->sum('total_amount'),
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('My Expenses') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Record and submit your expenses for approval.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-expense-modal" @disabled($employee === null) class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer @if($employee === null) opacity-50 cursor-not-allowed @endif">
        <i class="ph ph-plus"></i> {{ __('New Expense') }}
    </button>
</div>

@if ($employee === null)
    <div class="bg-warning-transparent text-warning border border-warning rounded-md px-4 py-3 text-sm mb-4">
        {{ __('You need an HR profile before submitting expenses. Contact your Tenant Admin.') }}
    </div>
@endif
@if (session('status'))<div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">{{ __('To Submit') }}</div><div class="text-2xl font-bold text-default">{{ number_format((float) $totals['to_submit'], 2) }} <span class="text-sm">TRY</span></div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">{{ __('Waiting Approval') }}</div><div class="text-2xl font-bold text-warning">{{ number_format((float) $totals['waiting'], 2) }} <span class="text-sm">TRY</span></div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">{{ __('Approved / Posted') }}</div><div class="text-2xl font-bold text-success">{{ number_format((float) $totals['approved'], 2) }} <span class="text-sm">TRY</span></div></div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Description') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Paid By') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $e)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->expense_date->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold">{{ $e->category?->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->description }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">{{ number_format((float) $e->total_amount, 2) }} <span class="text-[10px] text-default">{{ $e->currency_code }}</span></td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ __('paid-by.'.$e->paid_by) }}</td>
                        <td class="py-2.5 px-2 text-sm">
                            <span class="text-[11px] {{ $statusBadge($e->status) }} px-2 py-0.5 rounded">{{ __('expense-status.'.$e->status) }}</span>
                            @if ($e->status === 'refused' && $e->refuse_reason)<div class="text-[10px] text-danger mt-1">{{ $e->refuse_reason }}</div>@endif
                        </td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="flex items-center gap-1 justify-end flex-wrap">
                                @if ($e->receipt_path)
                                    <a href="{{ \Storage::url($e->receipt_path) }}" target="_blank" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light" title="{{ __('View receipt') }}"><i class="ph ph-image"></i></a>
                                @endif
                                @if ($e->isEditable())
                                    <button type="button" data-hs-overlay="#edit-expense-modal-{{ $e->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light" title="{{ __('Edit') }}"><i class="ph ph-pencil-simple"></i></button>
                                    <form method="POST" action="{{ route('app.expenses.submit', $e) }}" class="inline">
                                        @csrf
                                        <button class="btn-sm bg-primary text-white px-2 py-1 text-xs">{{ __('Submit') }}</button>
                                    </form>
                                @endif
                                @if ($e->status === 'draft')
                                    <form method="POST" action="{{ route('app.expenses.destroy', $e) }}" class="inline" onsubmit="return confirm('{{ __('Delete?') }}');">
                                        @csrf @method('DELETE')
                                        <button class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light"><i class="ph ph-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No expenses yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($employee !== null)
    @include('expenses::my._form-modal', ['id' => 'add-expense-modal', 'action' => route('app.expenses.store'), 'method' => 'POST', 'title' => __('New Expense'), 'expense' => null, 'categories' => $categories])
    @foreach ($expenses->filter->isEditable() as $e)
        @include('expenses::my._form-modal', ['id' => 'edit-expense-modal-'.$e->id, 'action' => route('app.expenses.update', $e), 'method' => 'PUT', 'title' => __('Edit Expense'), 'expense' => $e, 'categories' => $categories])
    @endforeach
@endif
@endsection
