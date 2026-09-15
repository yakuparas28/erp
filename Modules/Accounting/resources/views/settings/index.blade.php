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

<div class="bg-white border border-border-color rounded-md p-4 max-w-3xl">
    <h2 class="text-base font-bold text-title mb-2">{{ __('Document Approval Thresholds') }}</h2>
    <p class="text-[12px] text-default mb-4">
        {{ __('Any document whose subtotal is at or above the threshold below has to pass through the matching approval workflow (configured under Onay Akışları) before the next transition. Leave a field empty to disable that workflow.') }}
    </p>

    <form method="POST" action="{{ route('app.accounting.settings.update') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        @method('PATCH')

        <div>
            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quotation Threshold') }} <span class="text-[11px] text-default font-normal">({{ __('workflow subject: quotation') }})</span></label>
            <input type="number" step="0.01" min="0" name="quotation_approval_threshold"
                   value="{{ old('quotation_approval_threshold', $tenant->quotation_approval_threshold) }}"
                   placeholder="{{ __('e.g. 50000') }}"
                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <p class="text-[11px] text-default mt-1">{{ __('Blocks Send Quotation until approved.') }}</p>
            @error('quotation_approval_threshold')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Sales Order Threshold') }} <span class="text-[11px] text-default font-normal">({{ __('workflow subject: sales_order') }})</span></label>
            <input type="number" step="0.01" min="0" name="sales_order_approval_threshold"
                   value="{{ old('sales_order_approval_threshold', $tenant->sales_order_approval_threshold) }}"
                   placeholder="{{ __('e.g. 100000') }}"
                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <p class="text-[11px] text-default mt-1">{{ __('Blocks Convert to Order until approved.') }}</p>
            @error('sales_order_approval_threshold')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Purchase Order Threshold') }} <span class="text-[11px] text-default font-normal">({{ __('workflow subject: purchase_order') }})</span></label>
            <input type="number" step="0.01" min="0" name="purchase_order_approval_threshold"
                   value="{{ old('purchase_order_approval_threshold', $tenant->purchase_order_approval_threshold) }}"
                   placeholder="{{ __('e.g. 75000') }}"
                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <p class="text-[11px] text-default mt-1">{{ __('Blocks PO Confirmation until approved.') }}</p>
            @error('purchase_order_approval_threshold')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Invoice Threshold') }} <span class="text-[11px] text-default font-normal">({{ __('workflow subject: invoice') }})</span></label>
            <input type="number" step="0.01" min="0" name="invoice_approval_threshold"
                   value="{{ old('invoice_approval_threshold', $tenant->invoice_approval_threshold) }}"
                   placeholder="{{ __('e.g. 25000') }}"
                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <p class="text-[11px] text-default mt-1">{{ __('Blocks Post Invoice until approved.') }}</p>
            @error('invoice_approval_threshold')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2 pt-2">
            <button type="submit" class="btn-sm bg-primary text-white hover:bg-primary/90 inline-flex items-center gap-2">
                <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
            </button>
            <p class="text-[11px] text-default mt-2">
                {{ __('Reminder: define a matching workflow for each subject type on the Onay Akışları page — otherwise submissions will error with "no workflow configured".') }}
            </p>
        </div>
    </form>
</div>
@endsection
