@extends('app.layouts.app')

@section('title', __('Consumption & Accrual Rules'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Consumption & Accrual Rules') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Legal / policy rules the leave balance engine follows (BR-IT codes).') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-rule-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Rule') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Legal Basis') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $r)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 font-mono text-xs text-primary">{{ $r->code }}</td>
                        <td class="py-2.5 px-3">
                            @if ($r->category === 'consumption')
                                <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Consumption') }}</span>
                            @else
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Accrual') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 font-semibold text-title">{{ $r->name }}</td>
                        <td class="py-2.5 px-3 text-xs text-default">{{ Str::limit($r->legal_basis, 80) ?? '—' }}</td>
                        <td class="py-2.5 px-3">
                            @if ($r->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" data-hs-overlay="#edit-rule-modal-{{ $r->id }}" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form method="POST" action="{{ route('app.hr.consumption-rules.destroy', $r) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No rules defined yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@php
    $renderModal = function ($id, $action, $title, $method = null, $rule = null) {
        return compact('id', 'action', 'title', 'method', 'rule');
    };
@endphp

@include('hr::leaves._rule-modal', ['id' => 'add-rule-modal', 'action' => route('app.hr.consumption-rules.store'), 'title' => __('New Rule'), 'rule' => null])

@foreach ($rules as $r)
    @include('hr::leaves._rule-modal', ['id' => 'edit-rule-modal-'.$r->id, 'action' => route('app.hr.consumption-rules.update', $r), 'title' => __('Edit Rule'), 'rule' => $r, 'method' => 'PATCH'])
@endforeach
@endsection
