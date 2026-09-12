@extends('app.layouts.app')

@section('title', __('Quotations'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Quotations') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Draft and sent quotations. Convert to a sales order when the customer confirms.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-quotation-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Quotation') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Number') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Line Count') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Total (Net)') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created By') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $q)
                    @php $total = $q->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0'); @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-mono font-semibold text-title">SO-{{ str_pad((string) $q->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="py-2.5 px-3 text-sm text-title">{{ $q->partner->name }}</td>
                        <td class="py-2.5 px-3">@include('sales::orders._status-badge', ['status' => $q->status])</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $q->lines->count() }}</td>
                        <td class="py-2.5 px-3 text-sm font-semibold text-title text-right">{{ $total }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $q->creator?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.sales.orders.show', $q) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                {{ __('Open') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No quotations yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-quotation-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.sales.quotations.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Quotation') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-quotation-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 flex flex-col gap-3">
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Customer') }} <span class="text-danger">*</span></label>
                    <select name="partner_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Delivery From') }} <span class="text-danger">*</span></label>
                    <select name="location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-quotation-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Create') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
