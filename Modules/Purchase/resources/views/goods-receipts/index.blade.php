@extends('app.layouts.app')

@section('title', __('Goods Receipts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Goods Receipts') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Automatically generated for each received purchase order line.') }}
        </p>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Receipt No') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Purchase Order') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Supplier') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Warehouse') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Lines') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                <tr class="border-b border-border-color">
                    <td class="py-2 font-medium text-gray-900">{{ $receipt->receipt_no }}</td>
                    <td class="py-2 text-default">{{ optional($receipt->receipt_date)->format('d.m.Y') }}</td>
                    <td class="py-2">
                        <a href="{{ route('app.purchase.orders.show', $receipt->purchase_order_id) }}" class="text-primary hover:underline">PO-{{ $receipt->purchase_order_id }}</a>
                    </td>
                    <td class="py-2 text-gray-900">{{ optional($receipt->purchaseOrder?->partner)->name ?? '—' }}</td>
                    <td class="py-2 text-default">{{ optional($receipt->warehouseLocation)->name ?? '—' }}</td>
                    <td class="py-2 text-right text-default">{{ $receipt->lines->count() }}</td>
                    <td class="py-2">
                        <span class="px-2 py-0.5 rounded text-[11px] {{ $receipt->status === 'received' ? 'bg-success-transparent text-success' : ($receipt->status === 'cancelled' ? 'bg-danger-transparent text-danger' : 'bg-light text-default') }}">
                            {{ __(ucfirst($receipt->status)) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <a href="{{ route('app.purchase.goods-receipts.show', $receipt) }}" class="text-primary text-[12px] hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-6 text-center text-default">{{ __('No goods receipts yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $receipts->links() }}</div>
</div>
@endsection
