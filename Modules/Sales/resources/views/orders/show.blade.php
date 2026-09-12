@extends('app.layouts.app')

@section('title', __('Sales Order'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div class="flex items-center gap-3">
        <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Sales Order') }} <span class="font-mono text-default">SO-{{ str_pad((string) $so->id, 5, '0', STR_PAD_LEFT) }}</span></h1>
        @include('sales::orders._status-badge', ['status' => $so->status])
    </div>
    <div class="flex items-center gap-2">
        @if (in_array($so->status, ['quotation_sent', 'confirmed', 'done']))
            <a href="{{ route('app.sales.orders.quotation', $so) }}" target="_blank" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                <i class="ph ph-file-text"></i> {{ __('Quotation') }}
            </a>
        @endif
        @if (in_array($so->status, ['confirmed', 'done']))
            <a href="{{ route('app.sales.orders.delivery-slip', $so) }}" target="_blank" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                <i class="ph ph-printer"></i> {{ __('Delivery Slip') }}
            </a>
        @endif
        <a href="{{ route('app.sales.orders.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
            <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
        </a>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-5 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <h2 class="text-base font-bold text-title mb-4 flex items-center gap-2"><i class="ph-duotone ph-user-circle"></i> {{ __('Customer') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Name') }}</dt>
                    <dd class="font-semibold text-title">{{ $so->partner->name }}</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Tax No') }}</dt>
                    <dd class="text-title font-mono">{{ $so->partner->tax_number ?: '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Payment Term') }}</dt>
                    <dd class="text-title">{{ $so->partner->payment_term_days ?? 0 }} {{ __('days') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Delivery From') }}</dt>
                    <dd class="text-title">{{ $so->location?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <h2 class="text-base font-bold text-title mb-4 flex items-center gap-2"><i class="ph-duotone ph-receipt"></i> {{ __('Order Details') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Order ID') }}</dt>
                    <dd class="font-mono font-semibold text-title">SO-{{ str_pad((string) $so->id, 5, '0', STR_PAD_LEFT) }}</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Status') }}</dt>
                    <dd>@include('sales::orders._status-badge', ['status' => $so->status])</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Created By') }}</dt>
                    <dd class="text-title">{{ $so->creator?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Created At') }}</dt>
                    <dd class="text-title font-mono">{{ $so->created_at->format('d.m.Y H:i') }}</dd>
                </div>
                <div class="flex justify-between border-b border-border-color pb-2">
                    <dt class="text-default">{{ __('Last Updated') }}</dt>
                    <dd class="text-title font-mono">{{ $so->updated_at->format('d.m.Y H:i') }}</dd>
                </div>
                @if ($so->sent_at)
                    <div class="flex justify-between border-b border-border-color pb-2">
                        <dt class="text-default">{{ __('Quotation Sent At') }}</dt>
                        <dd class="text-title font-mono">{{ $so->sent_at->format('d.m.Y H:i') }}</dd>
                    </div>
                @endif
                @if ($so->validity_date)
                    <div class="flex justify-between border-b border-border-color pb-2">
                        <dt class="text-default">{{ __('Valid Until') }}</dt>
                        <dd class="text-title font-mono {{ $so->validity_date->isPast() ? 'text-danger' : '' }}">{{ $so->validity_date->format('d.m.Y') }}</dd>
                    </div>
                @endif
                @php
                    $totalNet = $so->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0');
                @endphp
                <div class="flex justify-between pt-1">
                    <dt class="text-default font-semibold">{{ __('Total (Net)') }}</dt>
                    <dd class="text-title font-bold text-lg">{{ $totalNet }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

@php
    $uomOptions = $products->pluck('uom')->filter()->unique('id');
@endphp
@if ($so->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Add Line') }}</h2>
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
@error('configurator')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

@if ($so->status === 'draft' && $configurableTemplates->isNotEmpty())
    @include('sales::orders._variant-configurator', ['so' => $so, 'templates' => $configurableTemplates, 'uomOptions' => $uomOptions])
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reserved') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Delivered') }}</th>
                    @if ($so->status === 'confirmed')
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($so->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->qty }} {{ $line->uom->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->unit_price }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            {{ $line->reserved_qty }}
                            @if ($line->product->reservation_method === 'manual')
                                <span class="text-[10px] bg-warning-transparent text-warning px-1 py-0.5 rounded ms-1">{{ __('manual') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->delivered_qty }}</td>
                        @if ($so->status === 'confirmed')
                            <td class="py-2.5 px-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    @php
                                        $unreservedRemaining = bcsub((string) $line->qty, bcadd((string) $line->reserved_qty, (string) $line->delivered_qty, 4), 4);
                                    @endphp
                                    @if ($line->product->reservation_method === 'manual' && bccomp($unreservedRemaining, '0', 4) > 0)
                                        <form method="POST" action="{{ route('app.sales.lines.reserve', $line) }}" class="flex items-end gap-1">
                                            @csrf
                                            <input type="number" step="0.0001" min="0.0001" max="{{ $unreservedRemaining }}" name="qty" value="{{ $unreservedRemaining }}" required class="w-20 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <button type="submit" class="btn-sm bg-white border border-info text-info hover:bg-info hover:text-white cursor-pointer">{{ __('Reserve') }}</button>
                                        </form>
                                    @endif
                                    @if (bccomp((string) $line->reserved_qty, '0', 4) > 0)
                                        <form method="POST" action="{{ route('app.sales.lines.unreserve', $line) }}">
                                            @csrf
                                            <button type="submit" class="btn-sm bg-white border border-border-color text-default hover:bg-light cursor-pointer" title="{{ __('Unreserve') }}"><i class="ph ph-lock-open text-xs"></i></button>
                                        </form>
                                    @endif
                                    @if (bccomp($line->delivered_qty, (string) $line->qty, 4) < 0)
                                        <form method="POST" action="{{ route('app.sales.lines.deliver', $line) }}" class="flex items-end gap-1">
                                            @csrf
                                            <input type="number" step="0.0001" min="0.0001" name="qty" required placeholder="{{ __('Quantity') }}" class="w-20 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Deliver') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
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
        <form method="POST" action="{{ route('app.sales.orders.send-quotation', $so) }}" class="flex items-end gap-2">
            @csrf
            <div>
                <label class="text-xs text-default mb-1 block">{{ __('Valid Until') }}</label>
                <input type="date" name="validity_date" min="{{ now()->toDateString() }}" value="{{ now()->addDays(30)->toDateString() }}" class="w-40 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">
                <i class="ph ph-paper-plane-tilt"></i> {{ __('Send Quotation') }}
            </button>
        </form>
        <form method="POST" action="{{ route('app.sales.orders.cancel', $so) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}')">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @elseif ($so->status === 'quotation_sent')
        @if ($canConfirm)
            <form method="POST" action="{{ route('app.sales.orders.confirm', $so) }}">
                @csrf
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-check-circle"></i> {{ __('Convert to Order') }}
                </button>
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
