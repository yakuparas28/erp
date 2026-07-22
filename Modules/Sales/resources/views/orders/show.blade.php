@extends('app.layouts.app')

@section('title', __('Sales Order'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Sales Order') }} — {{ $so->partner->name }}</h1>
        @include('sales::orders._status-badge', ['status' => $so->status])
    </div>
    <a href="{{ route('app.sales.orders.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@php
    $uomOptions = $products->pluck('uom')->filter()->unique('id');
@endphp
@if ($so->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Add Line') }}</h2>
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
        @error('qty')
            <p class="text-[11px] text-danger mt-2 mb-0">{{ $message }}</p>
        @enderror
    </div>
    <script>
        (function () {
            var productSelect = document.getElementById('line-product-select');
            var uomSelect = document.getElementById('line-uom-select');

            if (!productSelect || !uomSelect) {
                return;
            }

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

                    if (matches && !firstVisibleOption) {
                        firstVisibleOption = option;
                    }

                    if (matches && option.selected) {
                        currentOptionStillVisible = true;
                    }
                });

                if (!currentOptionStillVisible) {
                    var preferredOption = preferredUomId
                        ? Array.prototype.find.call(uomSelect.options, function (option) {
                            return option.value === preferredUomId;
                        })
                        : null;

                    uomSelect.value = preferredOption ? preferredOption.value : (firstVisibleOption ? firstVisibleOption.value : '');
                }
            }

            productSelect.addEventListener('change', syncUomOptions);
            syncUomOptions();
        })();
    </script>
@endif

@error('so')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Delivered') }}</th>
                    @if ($so->status === 'confirmed')
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Deliver') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($so->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->qty }} {{ $line->uom->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->unit_price }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->delivered_qty }}</td>
                        @if ($so->status === 'confirmed')
                            <td class="py-2.5 px-3">
                                @if (bccomp($line->delivered_qty, (string) $line->qty, 4) < 0)
                                    <form method="POST" action="{{ route('app.sales.lines.deliver', $line) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <input type="number" step="0.0001" min="0.0001" name="qty" required placeholder="{{ __('Quantity') }}" class="w-24 px-2 py-1.5 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Deliver') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="flex items-center gap-2 mt-4">
    @can('post journal entries')
        @if (in_array($so->status, ['confirmed', 'done']))
            <form method="POST" action="{{ route('app.accounting.sales-invoices.store') }}">
                @csrf
                <input type="hidden" name="sales_order_id" value="{{ $so->id }}">
                <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
                    <i class="ph ph-receipt"></i> {{ __('Create Invoice') }}
                </button>
            </form>
        @endif
    @endcan
    @if ($so->status === 'draft')
        <form method="POST" action="{{ route('app.sales.orders.send-quotation', $so) }}">
            @csrf
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Send Quotation') }}</button>
        </form>
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @elseif ($so->status === 'quotation_sent')
        @if ($canConfirm)
            <form method="POST" action="{{ route('app.sales.orders.confirm', $so) }}">
                @csrf
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Confirm') }}</button>
            </form>
        @else
            <p class="text-sm text-default">{{ __('Waiting for confirmation from another user (you cannot confirm your own sales order).') }}</p>
        @endif
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @elseif ($so->status === 'confirmed')
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @endif
</div>
@endsection
