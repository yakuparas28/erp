@extends('app.layouts.app')

@section('title', __('Purchase Order').' #PO'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT))

@section('content')
@php
    $poNo = '#PO'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT);
    $subtotal = $po->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0');
    $uomOptions = $products->pluck('uom')->filter()->unique('id');
    $tenant = auth()->user()->tenant;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-default mb-1">
            <a href="{{ route('app.purchase.orders.index') }}" class="hover:text-primary">{{ __('Purchase Orders') }}</a>
            <i class="ph ph-caret-right text-[10px]"></i>
            <span class="font-mono">{{ $poNo }}</span>
        </div>
        <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Purchase Order Details') }}</h1>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-1"><i class="ph ph-printer"></i> {{ __('Print') }}</button>
        <a href="{{ route('app.purchase.orders.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
            <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
        </a>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('po')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md p-5 sm:p-8 w-full mb-4">
    <div class="flex justify-between items-start mb-6 flex-wrap gap-5 lg:flex-nowrap">
        <div>
            <div class="text-lg font-bold text-title">{{ $tenant?->name ?? config('app.name') }}</div>
            @if ($tenant?->address)<div class="text-sm text-default mt-1">{{ $tenant->address }}</div>@endif
            @if ($tenant?->tax_number)<div class="text-sm text-default mt-1">{{ __('Tax No') }}: <span class="font-mono">{{ $tenant->tax_number }}</span></div>@endif
        </div>
        <div class="text-start sm:text-right">
            <h2 class="text-xl font-bold text-title uppercase mb-1">{{ __('Purchase Order') }}</h2>
            <p class="text-sm text-default mb-0 font-mono">{{ $poNo }}</p>
            <div class="mt-2">@include('purchase::orders._status-badge', ['status' => $po->status])</div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 pb-6 border-b border-border-color">
        <div>
            <p class="text-sm text-default mb-2 font-semibold">{{ __('Bill To') }}</p>
            <p class="text-sm font-semibold text-title mb-1">{{ $po->partner->name }}</p>
            @if ($po->partner->tax_number)<p class="text-xs text-default mb-1">{{ __('Tax No') }}: <span class="font-mono">{{ $po->partner->tax_number }}</span></p>@endif
            @if ($po->partner->email)<p class="text-xs text-default mb-0">{{ $po->partner->email }}</p>@endif
        </div>
        <div class="text-start sm:text-right text-sm space-y-2">
            <div><span class="text-default">{{ __('Purchase Date') }}:</span> <span class="text-gray-900 font-semibold">{{ $po->created_at->translatedFormat('d M Y') }}</span></div>
            <div><span class="text-default">{{ __('Created By') }}:</span> <span class="text-gray-900 font-semibold">{{ $po->creator?->name ?? '—' }}</span></div>
            @if ($po->partner->payment_term_days)
                <div><span class="text-default">{{ __('Payment Term') }}:</span> <span class="text-gray-900 font-semibold">{{ $po->partner->payment_term_days }} {{ __('days') }}</span></div>
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
                    <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Received') }}</th>
                    @if ($po->status === 'confirmed')
                        <th class="text-right py-3 px-3 font-semibold text-gray-900">{{ __('Receive') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($po->lines as $line)
                    @php $lineAmount = bcmul((string) $line->qty, (string) $line->unit_price, 4); @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-3 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-3 px-3 text-sm text-right">{{ $line->qty }} <span class="text-default text-xs">{{ $line->uom->name }}</span></td>
                        <td class="py-3 px-3 text-sm text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="py-3 px-3 text-sm text-right font-semibold">{{ number_format((float) $lineAmount, 2) }}</td>
                        <td class="py-3 px-3 text-sm text-right text-default">{{ $line->receivedQty() }}</td>
                        @if ($po->status === 'confirmed')
                            <td class="py-3 px-3">
                                @if (bccomp($line->receivedQty(), (string) $line->qty, 4) < 0)
                                    <form method="POST" action="{{ route('app.purchase.lines.receive', $line) }}" class="flex flex-wrap items-end justify-end gap-1">
                                        @csrf
                                        <input type="number" step="0.0001" min="0.0001" name="qty" required placeholder="{{ __('Qty') }}" class="w-20 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                        <select name="receiving_location_id" required class="w-36 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer text-xs">{{ __('Receive') }}</button>
                                    </form>
                                @else
                                    <span class="text-xs text-success">✓ {{ __('Complete') }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $po->status === 'confirmed' ? 6 : 5 }}" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap justify-between items-end gap-3">
        @php
            $hasReceivable = $po->status === 'confirmed' && $po->lines->contains(fn ($l) => bccomp($l->receivedQty(), (string) $l->qty, 4) < 0);
        @endphp
        <div>
            @if ($hasReceivable)
                <button type="button" data-po-open-receipt class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-package"></i> {{ __('Create Goods Receipt') }}
                </button>
            @endif
        </div>
        <div class="w-72 text-sm space-y-2">
            <div class="flex justify-between"><span class="text-default">{{ __('Subtotal') }}</span><span class="text-gray-900 font-semibold">{{ number_format((float) $subtotal, 2) }}</span></div>
            <div class="flex justify-between border-t border-border-color pt-2 text-base"><span class="font-bold text-title">{{ __('Total') }}</span><span class="text-primary font-bold">{{ number_format((float) $subtotal, 2) }}</span></div>
        </div>
    </div>
</div>

@if ($po->status === 'confirmed')
    @include('purchase::orders._goods-receipt-modal', ['po' => $po, 'locations' => $locations])
@endif

@if ($po->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-title mb-0 inline-flex items-center gap-2"><i class="ph ph-plus-circle"></i> {{ __('Add Line') }}</h2>
        </div>
        <form method="POST" action="{{ route('app.purchase.orders.lines.store', $po) }}" class="flex flex-wrap items-end gap-3">
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

@include('app.partials.related-shipments', ['items' => $goodsReceipts, 'mode' => 'purchase'])
@include('app.partials.related-invoices', ['invoices' => $relatedInvoices, 'routePrefix' => 'purchase'])

@php
    $purchaseSvc = app(\Modules\Purchase\Services\PurchaseOrderService::class);
    $canAdmin = auth()->user()->hasRole('Tenant Admin');
@endphp

@if ($po->status === 'rfq_sent' && $purchaseSvc->requiresConfirmationApproval($po))
    @include('app.partials.approval-panel', [
        'required' => true,
        'approval' => $po->approvalFor('purchase_order'),
        'canApprove' => $canAdmin,
        'canSubmit' => true,
        'title' => __('Purchase Order Approval Required'),
        'submitRoute' => route('app.purchase.orders.approval.submit', $po),
        'approveRoute' => route('app.purchase.orders.approval.approve', $po),
        'rejectRoute' => route('app.purchase.orders.approval.reject', $po),
    ])
@endif

<div class="flex items-center gap-2 flex-wrap mt-4">
    @can('post journal entries')
        @if (in_array($po->status, ['confirmed', 'done']))
            <form method="POST" action="{{ route('app.accounting.purchase-invoices.store') }}">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $po->id }}">
                <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
                    <i class="ph ph-receipt"></i> {{ __('Create Invoice') }}
                </button>
            </form>
        @endif
    @endcan
    @if ($po->status === 'draft')
        <form method="POST" action="{{ route('app.purchase.orders.send-rfq', $po) }}">
            @csrf
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send RFQ') }}</button>
        </form>
        <form method="POST" action="{{ route('app.purchase.orders.cancel', $po) }}" onsubmit="return confirm('{{ __('Cancel this purchase order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @elseif ($po->status === 'rfq_sent')
        @if ($canConfirm)
            <form method="POST" action="{{ route('app.purchase.orders.confirm', $po) }}">
                @csrf
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2"><i class="ph ph-check-circle"></i> {{ __('Confirm') }}</button>
            </form>
        @else
            <p class="text-sm text-default mb-0">{{ __('Waiting for confirmation from another user (you cannot confirm your own purchase order).') }}</p>
        @endif
        <form method="POST" action="{{ route('app.purchase.orders.cancel', $po) }}" onsubmit="return confirm('{{ __('Cancel this purchase order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @elseif ($po->status === 'confirmed')
        <form method="POST" action="{{ route('app.purchase.orders.cancel', $po) }}" onsubmit="return confirm('{{ __('Cancel this purchase order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2"><i class="ph ph-x-circle"></i> {{ __('Cancel') }}</button>
        </form>
    @endif
</div>
@endsection
