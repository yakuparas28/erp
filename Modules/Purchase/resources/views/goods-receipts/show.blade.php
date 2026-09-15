@extends('app.layouts.app')

@section('title', $receipt->receipt_no)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Goods Receipt') }} — {{ $receipt->receipt_no }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Purchase Order') }}
            <a href="{{ route('app.purchase.orders.show', $receipt->purchase_order_id) }}" class="text-primary hover:underline">PO-{{ $receipt->purchase_order_id }}</a>
            · {{ optional($receipt->purchaseOrder?->partner)->name }}
        </p>
    </div>
    <a href="{{ route('app.purchase.goods-receipts.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2">
        <i class="ph ph-arrow-left"></i> {{ __('Back') }}
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Date') }}</div>
        <div class="text-gray-900 font-medium">{{ optional($receipt->receipt_date)->format('d.m.Y') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Warehouse') }}</div>
        <div class="text-gray-900 font-medium">{{ optional($receipt->warehouseLocation)->name ?? '—' }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Waybill No') }}</div>
        <div class="text-gray-900 font-medium">{{ $receipt->waybill_no ?: '—' }}</div>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <h2 class="text-gray-900 font-semibold mb-3">{{ __('Lines') }}</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Product') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('UoM') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt->lines as $line)
                <tr class="border-b border-border-color">
                    <td class="py-2 text-gray-900">{{ optional($line->product)->name }}</td>
                    <td class="py-2 text-right text-gray-900">{{ number_format((float) $line->qty, 4, ',', '.') }}</td>
                    <td class="py-2 text-default">{{ optional($line->uom)->name }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($receipt->notes)
    <div class="mt-4 bg-light rounded-md p-3">
        <div class="text-[11px] uppercase text-default mb-1">{{ __('Notes') }}</div>
        <div class="text-sm text-gray-900 whitespace-pre-wrap">{{ $receipt->notes }}</div>
    </div>
    @endif
</div>
@endsection
