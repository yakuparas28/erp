@extends('app.layouts.app')

@section('title', __('Quotation'))

@section('content')
<style>
    @media print {
        body * { visibility: hidden !important; }
        #print-area, #print-area * { visibility: visible !important; }
        #print-area {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 24px !important;
            border: 0 !important;
            box-shadow: none !important;
            background: white !important;
        }
        .no-print { display: none !important; }
        @page { size: A4; margin: 12mm; }
    }
</style>
<div id="print-area" class="max-w-3xl mx-auto bg-white border border-border-color rounded-md p-8">
    <div class="flex items-start justify-between mb-6 no-print">
        <a href="{{ route('app.sales.orders.show', $so) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
            <i class="ph ph-arrow-left"></i> {{ __('Back to Order') }}
        </a>
        <button type="button" onclick="window.print()" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
            <i class="ph ph-printer"></i> {{ __('Print') }}
        </button>
    </div>

    <div class="flex items-start justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-title mb-1">{{ __('Quotation') }}</h1>
            <p class="text-sm text-default mb-0">SO-{{ str_pad((string) $so->id, 5, '0', STR_PAD_LEFT) }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-default mb-0">{{ __('Date') }}</p>
            <p class="text-sm font-semibold text-title mb-2">{{ $so->created_at->format('d.m.Y') }}</p>
            @if ($so->validity_date)
                <p class="text-xs text-default mb-0">{{ __('Valid Until') }}</p>
                <p class="text-sm font-semibold text-title">{{ $so->validity_date->format('d.m.Y') }}</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-8 pb-6 border-b border-border-color">
        <div>
            <h3 class="text-xs font-semibold text-default uppercase mb-2">{{ __('From') }}</h3>
            <p class="text-sm font-semibold text-title mb-0">{{ $so->tenant->name ?? config('app.name') }}</p>
            @if (! empty($so->tenant?->tax_number)) <p class="text-xs text-default mb-0">{{ __('Tax No') }}: {{ $so->tenant->tax_number }}</p> @endif
            @if (! empty($so->tenant?->address)) <p class="text-xs text-default mb-0">{{ $so->tenant->address }}</p> @endif
        </div>
        <div>
            <h3 class="text-xs font-semibold text-default uppercase mb-2">{{ __('To') }}</h3>
            <p class="text-sm font-semibold text-title mb-0">{{ $so->partner->name }}</p>
            @if (! empty($so->partner->tax_number)) <p class="text-xs text-default mb-0">{{ __('Tax No') }}: {{ $so->partner->tax_number }}</p> @endif
            @if (! empty($so->partner->email)) <p class="text-xs text-default mb-0">{{ $so->partner->email }}</p> @endif
            @if (! empty($so->partner->phone)) <p class="text-xs text-default mb-0">{{ $so->partner->phone }}</p> @endif
            @if (! empty($so->partner->address)) <p class="text-xs text-default mb-0">{{ $so->partner->address }}</p> @endif
        </div>
    </div>

    <table class="w-full text-sm mb-6">
        <thead>
            <tr class="border-b border-border-color">
                <th class="text-left py-2 font-semibold text-title">{{ __('Item') }}</th>
                <th class="text-right py-2 font-semibold text-title">{{ __('Qty') }}</th>
                <th class="text-right py-2 font-semibold text-title">{{ __('Unit Price') }}</th>
                <th class="text-right py-2 font-semibold text-title">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($so->lines as $line)
                @php $lineTotal = bcmul((string) $line->qty, (string) $line->unit_price, 4); @endphp
                <tr class="border-b border-border-color">
                    <td class="py-2">{{ $line->product->name }}</td>
                    <td class="py-2 text-right">{{ $line->qty }} {{ $line->uom->name }}</td>
                    <td class="py-2 text-right">{{ $line->unit_price }}</td>
                    <td class="py-2 text-right font-semibold">{{ $lineTotal }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php $grandTotal = $so->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0'); @endphp
            <tr>
                <td colspan="3" class="py-3 text-right font-semibold text-title">{{ __('Total (Net)') }}</td>
                <td class="py-3 text-right font-bold text-lg text-title">{{ $grandTotal }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-8 text-xs text-default">
        <p class="mb-1">{{ __('This document is a non-binding quotation. Prices are exclusive of VAT unless stated otherwise.') }}</p>
        @if ($so->validity_date)
            <p class="mb-0">{{ __('This offer is valid until :date.', ['date' => $so->validity_date->format('d.m.Y')]) }}</p>
        @endif
    </div>
</div>
@endsection
