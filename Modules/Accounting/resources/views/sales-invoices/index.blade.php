@extends('app.layouts.app')

@section('title', __('Sales Invoices'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Sales Invoices') }}</h1>
    @if ($salesOrders->isNotEmpty())
        <button type="button" data-hs-overlay="#new-sales-invoice-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('New Sales Invoice') }}
        </button>
    @endif
</div>

@error('currency_id')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Currency') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Total') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $invoice->partner->name }}</td>
                        <td class="py-2.5 px-3">
                            @include('accounting::sales-invoices._status-badge', ['status' => $invoice->status])
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $invoice->currency?->code ?? 'TRY' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $invoice->total() }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.accounting.sales-invoices.show', $invoice) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No sales invoices yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($salesOrders->isNotEmpty())
    <div id="new-sales-invoice-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.accounting.sales-invoices.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('New Sales Invoice') }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#new-sales-invoice-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Sales Order') }} <span class="text-danger">*</span></label>
                        <select name="sales_order_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($salesOrders as $salesOrder)
                                <option value="{{ $salesOrder->id }}">#{{ $salesOrder->id }} — {{ $salesOrder->partner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Currency') }}</label>
                        <select name="currency_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">{{ __('TRY (Default)') }}</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#new-sales-invoice-modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
