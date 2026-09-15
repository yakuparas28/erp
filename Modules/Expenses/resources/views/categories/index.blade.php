@extends('app.layouts.app')

@section('title', __('Expense Categories'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Expense Categories') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Set unit price > 0 for flat-rate (per diem, mileage); leave 0 for actual cost.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-expense-category-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('Add New') }}
    </button>
</div>

@if (session('status'))<div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>@endif
@error('category')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Unit') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Expense Account') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-2.5 px-2 text-sm text-default font-mono">{{ $cat->code ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $cat->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-right">
                            @if ($cat->isFlatRate())
                                <span class="font-semibold text-title">{{ number_format((float) $cat->unit_price, 2) }}</span>
                            @else
                                <span class="text-default">{{ __('Actual') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $cat->unit_label }}</td>
                        <td class="py-2.5 px-2 text-sm text-default font-mono">{{ $cat->expense_account_code ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm">
                            @if ($cat->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="flex items-center gap-1 justify-end">
                                <button type="button" data-hs-overlay="#edit-expense-category-modal-{{ $cat->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}"><i class="ph ph-pencil-simple"></i></button>
                                <form method="POST" action="{{ route('app.expenses.categories.destroy', $cat) }}" onsubmit="return confirm('{{ __('Delete?') }}');">
                                    @csrf @method('DELETE')
                                    <button class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer"><i class="ph ph-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('expenses::categories._form-modal', ['id' => 'add-expense-category-modal', 'action' => route('app.expenses.categories.store'), 'method' => 'POST', 'title' => __('New Category'), 'category' => null])
@foreach ($categories as $cat)
    @include('expenses::categories._form-modal', ['id' => 'edit-expense-category-modal-'.$cat->id, 'action' => route('app.expenses.categories.update', $cat), 'method' => 'PUT', 'title' => __('Edit Category'), 'category' => $cat])
@endforeach
@endsection
