@extends('app.layouts.app')

@section('title', __('Sales Order').' #SO'.str_pad((string) $so->id, 5, '0', STR_PAD_LEFT))

@section('content')
@php
    $soNo = '#SO'.str_pad((string) $so->id, 5, '0', STR_PAD_LEFT);
    $subtotal = $so->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0');
    $uomOptions = $products->pluck('uom')->filter()->unique('id');
    $tenant = auth()->user()->tenant;
    $isQuotationPhase = in_array($so->status, ['draft', 'quotation_sent']);
    $backRoute = $isQuotationPhase ? 'app.sales.quotations.index' : 'app.sales.orders.index';
    $backLabel = $isQuotationPhase ? __('Quotations') : __('Sales Orders');
    $docTitle = $isQuotationPhase ? __('Sales Quote') : __('Sales Order');
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-default mb-1">
            <a href="{{ route($backRoute) }}" class="hover:text-primary">{{ $backLabel }}</a>
            <i class="ph ph-caret-right text-[10px]"></i>
            <span class="font-mono">{{ $soNo }}</span>
        </div>
        <h1 class="text-gray-900 text-xl font-bold mb-0">{{ $docTitle }} {{ __('Preview') }}</h1>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        @if ($so->customer_confirmed_at)
            <span class="inline-flex items-center gap-2 text-xs bg-success-transparent text-success border border-success rounded-md px-2 py-1">
                <i class="ph ph-check-circle"></i> {{ __('Customer confirmed the quotation on :date.', ['date' => $so->customer_confirmed_at->format('d.m.Y H:i')]) }}
            </span>
        @elseif ($so->customer_declined_at)
            <span class="inline-flex items-center gap-2 text-xs bg-danger-transparent text-danger border border-danger rounded-md px-2 py-1">
                <i class="ph ph-x-circle"></i> {{ __('Customer declined the quotation on :date.', ['date' => $so->customer_declined_at->format('d.m.Y H:i')]) }}
            </span>
        @endif
        @if (in_array($so->status, ['quotation_sent', 'confirmed', 'done']))
            <a href="{{ route('app.sales.orders.quotation', $so) }}" target="_blank" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-1"><i class="ph ph-file-text"></i> {{ __('Quotation') }}</a>
        @endif
        @if (in_array($so->status, ['confirmed', 'done']))
            <a href="{{ route('app.sales.orders.delivery-slip', $so) }}" target="_blank" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-1"><i class="ph ph-printer"></i> {{ __('Delivery Slip') }}</a>
        @endif
        <button type="button" onclick="window.print()" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-1"><i class="ph ph-printer"></i> {{ __('Print') }}</button>
        <a href="{{ route($backRoute) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light"><i class="ph ph-arrow-left"></i> {{ __('Back to List') }}</a>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('so')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror
@error('configurator')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="bg-white border border-border-color rounded-md p-5 sm:p-8 w-full mb-4">
    <div class="flex justify-between items-start mb-6 flex-wrap gap-5 lg:flex-nowrap">
        <div>
            <div class="text-lg font-bold text-title">{{ $tenant?->name ?? config('app.name') }}</div>
            @if ($tenant?->address)<div class="text-sm text-default mt-1">{{ $tenant->address }}</div>@endif
            @if ($tenant?->tax_number)<div class="text-sm text-default mt-1">{{ __('Tax No') }}: <span class="font-mono">{{ $tenant->tax_number }}</span></div>@endif
        </div>
        <div class="text-start sm:text-right">
            <h2 class="text-xl font-bold text-title uppercase mb-1">{{ $docTitle }}</h2>
            <p class="text-sm text-default mb-0 font-mono">{{ $soNo }}</p>
            <div class="mt-2">@include('sales::orders._status-badge', ['status' => $so->status])</div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 pb-6 border-b border-border-color">
        <div>
            <p class="text-sm text-default mb-2 font-semibold">{{ __('Bill To') }}</p>
            <p class="text-sm font-semibold text-title mb-1">{{ $so->partner->name }}</p>
            @if ($so->partner->tax_number)<p class="text-xs text-default mb-1">{{ __('Tax No') }}: <span class="font-mono">{{ $so->partner->tax_number }}</span></p>@endif
            @if ($so->partner->email)<p class="text-xs text-default mb-0">{{ $so->partner->email }}</p>@endif
        </div>
        <div class="text-start sm:text-right text-sm space-y-2">
            <div><span class="text-default">{{ __('Order Date') }}:</span> <span class="text-gray-900 font-semibold">{{ $so->created_at->translatedFormat('d M Y') }}</span></div>
            <div><span class="text-default">{{ __('Created By') }}:</span> <span class="text-gray-900 font-semibold">{{ $so->creator?->name ?? '—' }}</span></div>
            <div><span class="text-default">{{ __('Delivery From') }}:</span> <span class="text-gray-900 font-semibold">{{ $so->location?->name ?? '—' }}</span></div>
            @if ($so->partner->payment_term_days)
                <div><span class="text-default">{{ __('Payment Term') }}:</span> <span class="text-gray-900 font-semibold">{{ $so->partner->payment_term_days }} {{ __('days') }}</span></div>
            @endif
            @if ($so->sent_at)
                <div><span class="text-default">{{ __('Quotation Sent At') }}:</span> <span class="text-gray-900 font-semibold font-mono">{{ $so->sent_at->format('d.m.Y H:i') }}</span></div>
            @endif
            @if ($so->validity_date)
                <div><span class="text-default">{{ __('Valid Until') }}:</span> <span class="font-semibold font-mono {{ $so->validity_date->isPast() ? 'text-danger' : 'text-gray-900' }}">{{ $so->validity_date->format('d.m.Y') }}</span></div>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color bg-light">
                    <th class="text-left py-3 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Qty') }}</th>
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Reserved') }}</th>
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Delivered') }}</th>
                    @if ($so->status === 'confirmed')
                        <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($so->lines as $line)
                    @php $lineAmount = bcmul((string) $line->qty, (string) $line->unit_price, 4); @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-3 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-3 px-3 text-sm text-right">{{ $line->qty }} <span class="text-default text-xs">{{ $line->uom->name }}</span></td>
                        <td class="py-3 px-3 text-sm text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="py-3 px-3 text-sm text-right font-semibold">{{ number_format((float) $lineAmount, 2) }}</td>
                        <td class="py-3 px-3 text-sm text-right text-default">
                            {{ $line->reserved_qty }}
                            @if ($line->product->reservation_method === 'manual')
                                <span class="text-[10px] bg-warning-transparent text-warning px-1 py-0.5 rounded ms-1">{{ __('manual') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-sm text-right text-default">{{ $line->delivered_qty }}</td>
                        @if ($so->status === 'confirmed')
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap items-end justify-end gap-1">
                                    @php $unreservedRemaining = bcsub((string) $line->qty, bcadd((string) $line->reserved_qty, (string) $line->delivered_qty, 4), 4); @endphp
                                    @if ($line->product->reservation_method === 'manual' && bccomp($unreservedRemaining, '0', 4) > 0)
                                        <form method="POST" action="{{ route('app.sales.lines.reserve', $line) }}" class="flex items-end gap-1">
                                            @csrf
                                            <input type="number" step="0.0001" min="0.0001" max="{{ $unreservedRemaining }}" name="qty" value="{{ $unreservedRemaining }}" required class="w-20 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <button type="submit" class="btn-sm bg-white border border-info text-info hover:bg-info hover:text-white cursor-pointer text-xs">{{ __('Reserve') }}</button>
                                        </form>
                                    @endif
                                    @if (bccomp((string) $line->reserved_qty, '0', 4) > 0)
                                        <form method="POST" action="{{ route('app.sales.lines.unreserve', $line) }}">
                                            @csrf
                                            <button type="submit" class="btn-sm bg-white border border-border-color text-default hover:bg-light cursor-pointer text-xs" title="{{ __('Unreserve') }}"><i class="ph ph-lock-open text-xs"></i></button>
                                        </form>
                                    @endif
                                    @if (bccomp($line->delivered_qty, (string) $line->qty, 4) < 0)
                                        <form method="POST" action="{{ route('app.sales.lines.deliver', $line) }}" class="flex items-end gap-1">
                                            @csrf
                                            <input type="number" step="0.0001" min="0.0001" name="qty" required placeholder="{{ __('Qty') }}" class="w-20 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer text-xs">{{ __('Deliver') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $so->status === 'confirmed' ? 7 : 6 }}" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end">
        <div class="w-72 text-sm space-y-2">
            <div class="flex justify-between"><span class="text-default">{{ __('Subtotal') }}</span><span class="text-gray-900 font-semibold">{{ number_format((float) $subtotal, 2) }}</span></div>
            <div class="flex justify-between border-t border-border-color pt-2 text-base"><span class="font-bold text-title">{{ __('Total') }}</span><span class="text-primary font-bold">{{ number_format((float) $subtotal, 2) }}</span></div>
        </div>
    </div>
</div>

@if ($so->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h2 class="text-base font-bold text-title mb-0 inline-flex items-center gap-2"><i class="ph ph-plus-circle"></i> {{ __('Add Line') }}</h2>
            @if ($configurableTemplates->isNotEmpty())
                <button type="button" data-hs-overlay="#variant-configurator-modal" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
                    <i class="ph ph-sliders-horizontal"></i> {{ __('Configure Variant') }}
                </button>
            @endif
        </div>
        <form method="POST" action="{{ route('app.sales.orders.lines.store', $so) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }}</label>
                <select id="line-product-select" name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-uom-id="{{ $product->uom_id }}" data-uom-category-id="{{ $product->uom?->uom_category_id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }}</label>
                <select id="line-uom-select" name="uom_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($uomOptions as $uom)
                        <option value="{{ $uom->id }}" data-category-id="{{ $uom->uom_category_id }}">{{ $uom->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-28">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }}</label>
                <input type="number" step="0.0001" min="0.0001" name="qty" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="w-28">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit Price') }}</label>
                <input type="number" step="0.0001" min="0" name="unit_price" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
        </form>
        @error('qty')<p class="text-[11px] text-danger mt-2 mb-0">{{ $message }}</p>@enderror
    </div>
    <script>
        (function () {
            var productSelect = document.getElementById('line-product-select');
            var uomSelect = document.getElementById('line-uom-select');
            if (!productSelect || !uomSelect) return;
            function syncUomOptions() {
                var selectedProduct = productSelect.options[productSelect.selectedIndex];
                var categoryId = selectedProduct ? selectedProduct.getAttribute('data-uom-category-id') : null;
                var preferredUomId = selectedProduct ? selectedProduct.getAttribute('data-uom-id') : null;
                var firstVisibleOption = null;
                var currentOptionStillVisible = false;
                Array.prototype.forEach.call(uomSelect.options, function (option) {
                    var matches = !categoryId || option.getAttribute('data-category-id') === categoryId;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (matches && !firstVisibleOption) firstVisibleOption = option;
                    if (matches && option.selected) currentOptionStillVisible = true;
                });
                if (!currentOptionStillVisible) {
                    var preferredOption = preferredUomId
                        ? Array.prototype.find.call(uomSelect.options, function (option) { return option.value === preferredUomId; })
                        : null;
                    uomSelect.value = preferredOption ? preferredOption.value : (firstVisibleOption ? firstVisibleOption.value : '');
                }
            }
            productSelect.addEventListener('change', syncUomOptions);
            syncUomOptions();
        })();
    </script>
@endif

@include('app.partials.related-shipments', ['items' => $deliveryNotes, 'mode' => 'sales'])
@include('app.partials.related-invoices', ['invoices' => $relatedInvoices, 'routePrefix' => 'sales'])

@if ($so->status === 'draft' && $configurableTemplates->isNotEmpty())
    @include('sales::orders._variant-configurator', ['so' => $so, 'templates' => $configurableTemplates, 'uomOptions' => $uomOptions])
@endif

<div class="flex items-center gap-2 flex-wrap">
    @can('post journal entries')
        @if (in_array($so->status, ['confirmed', 'done']))
            <form method="POST" action="{{ route('app.accounting.sales-invoices.store') }}">
                @csrf
                <input type="hidden" name="sales_order_id" value="{{ $so->id }}">
                <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer"><i class="ph ph-receipt"></i> {{ __('Create Invoice') }}</button>
            </form>
        @endif
    @endcan
    @if ($so->status === 'draft')
        <form method="POST" action="{{ route('app.sales.orders.send-quotation', $so) }}" class="flex items-end gap-2">
            @csrf
            <div>
                <label class="text-xs text-default mb-1 block">{{ __('Valid Until') }}</label>
                <input type="date" name="validity_date" min="{{ now()->toDateString() }}" value="{{ now()->addDays(30)->toDateString() }}" class="w-40 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send Quotation') }}</button>
        </form>
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @elseif ($so->status === 'quotation_sent')
        @if ($canConfirm)
            <form method="POST" action="{{ route('app.sales.orders.confirm', $so) }}">
                @csrf
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2"><i class="ph ph-check-circle"></i> {{ __('Convert to Order') }}</button>
            </form>
        @else
            <p class="text-sm text-default mb-0">{{ __('Waiting for confirmation from another user (you cannot confirm your own sales order).') }}</p>
        @endif
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @elseif ($so->status === 'confirmed')
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @endif
</div>
@endsection
