@extends('app.layouts.app')

@section('title', __('Accounting Settings'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Accounting Settings') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Tenant-level rules for invoicing and approvals.') }}</p>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md p-4 max-w-2xl">
    <h2 class="text-base font-bold text-title mb-3">{{ __('Invoice Approval Threshold') }}</h2>
    <p class="text-[12px] text-default mb-3">
        {{ __('Purchase invoices with a subtotal at or above this amount must be approved before they can be posted. Leave empty to disable the workflow.') }}
    </p>
    <form method="POST" action="{{ route('app.accounting.settings.update') }}" class="flex items-end gap-3">
        @csrf
        @method('PATCH')
        <div class="flex-1 max-w-xs">
            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Threshold (TRY, subtotal)') }}</label>
            <input type="number" step="0.01" min="0" name="invoice_approval_threshold"
                   value="{{ old('invoice_approval_threshold', $tenant->invoice_approval_threshold) }}"
                   placeholder="{{ __('e.g. 25000') }}"
                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            @error('invoice_approval_threshold')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-sm bg-primary text-white hover:bg-primary/90 inline-flex items-center gap-2">
            <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
        </button>
    </form>
</div>
@endsection
