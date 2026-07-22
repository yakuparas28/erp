@extends('app.layouts.app')

@section('title', __('Purchase Invoice'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Purchase Invoice') }} — {{ $invoice->partner->name }}</h1>
        @include('accounting::purchase-invoices._status-badge', ['status' => $invoice->status])
        @if ($invoice->source)
            <a href="{{ route('app.purchase.orders.show', $invoice->source_id) }}" class="text-sm text-default hover:underline ms-2">
                {{ __('Purchase Order') }} #{{ $invoice->source->id }}
            </a>
        @endif
    </div>
    <a href="{{ route('app.accounting.purchase-invoices.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@error('invoice')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

@if ($invoice->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Add Line') }}</h2>
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.lines.store', $invoice) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }}</label>
                <select name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($invoice->source->lines as $poLine)
                        <option value="{{ $poLine->product_id }}">{{ $poLine->product->name }} ({{ __('ordered') }}: {{ $poLine->qty }}, {{ __('received') }}: {{ $poLine->receivedQty() }})</option>
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
            <div class="w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Rate') }}</label>
                <select name="tax_rate_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->id }}">{{ $taxRate->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
        </form>
        @error('qty')
            <p class="text-[11px] text-danger mt-2 mb-0">{{ $message }}</p>
        @enderror
    </div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tax Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Subtotal') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tax Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->qty }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->unit_price }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->taxRate?->name ?? __('None') }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->subtotal() }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->taxAmount() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($invoice->lines->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="4" class="py-2.5 px-3 text-sm font-semibold text-title text-right">{{ __('Total') }}</td>
                        <td colspan="2" class="py-2.5 px-3 text-sm font-semibold text-title">{{ $invoice->total() }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if ($invoice->status === 'draft' && $invoice->lines->isNotEmpty())
    <div class="flex items-center gap-2 mt-4">
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.post', $invoice) }}">
            @csrf
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Post') }}</button>
        </form>
    </div>
@endif
@endsection
