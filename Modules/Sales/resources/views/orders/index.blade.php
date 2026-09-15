@extends('app.layouts.app')

@section('title', __('Sales Orders'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Sales Orders') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Confirmed / delivered orders. Create new orders via the') }}
            <a href="{{ route('app.sales.quotations.index') }}" class="text-primary hover:underline">{{ __('Quotations') }}</a>
            {{ __('flow.') }}
        </p>
    </div>
    <div class="flex items-center flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph-duotone ph-printer"></i> {{ __('Print') }}
        </button>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <div class="relative w-64">
                <i class="ph ph-magnifying-glass absolute right-2.5 top-1/2 -translate-y-1/2 text-default text-sm"></i>
                <input type="text" id="so-search" class="w-full px-3 pe-8 py-2 h-7 text-[12px]! border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" placeholder="{{ __('Search') }}">
            </div>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-primary hover:border-primary hover:text-white focus:outline-hidden">
                    <i class="ph ph-funnel font-normal"></i> {{ __('Filter') }} <i class="ph ph-caret-down text-xs"></i>
                </button>
                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                    <div class="p-2 space-y-1">
                        @foreach (['confirmed', 'done', 'cancelled'] as $s)
                            <label class="flex items-center gap-2 text-sm cursor-pointer px-2 py-1.5 rounded-md hover:bg-light">
                                <input type="checkbox" value="{{ $s }}" data-so-status-filter class="size-4 rounded border-border-color text-primary focus:ring-0">
                                {{ __('so-status.'.$s) }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <button type="button" onclick="location.reload()" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Refresh') }}"><i class="ph ph-arrow-clockwise"></i></button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('SO ID') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Created By') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Order Date') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-center py-2 px-2 font-semibold text-gray-900">{{ __('Lines') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php $total = $order->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0'); @endphp
                    <tr class="border-b border-border-color hover:bg-light/50" data-so-row data-status="{{ $order->status }}" data-search="{{ strtolower($order->partner->name.' '.$order->creator?->name.' SO-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)) }}">
                        <td class="py-2.5 px-2 text-sm">
                            <a href="{{ route('app.sales.orders.show', $order) }}" class="font-mono text-default hover:text-primary">#SO{{ str_pad((string) $order->id, 5, '0', STR_PAD_LEFT) }}</a>
                        </td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $order->partner->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $order->creator?->name ?? '—' }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $order->created_at->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">{{ number_format((float) $total, 2) }}</td>
                        <td class="py-2.5 px-2 text-sm text-default text-center">{{ $order->lines_count }}</td>
                        <td class="py-2.5 px-2">
                            @include('sales::orders._status-badge', ['status' => $order->status])
                        </td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm size-7 bg-white border border-border-color text-gray-600 inline-flex items-center justify-center hover:bg-light hover:text-gray-900 focus:outline-hidden">
                                    <i class="ph ph-dots-three-vertical"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-40 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                                    <div class="p-2 space-y-1">
                                        <a href="{{ route('app.sales.orders.show', $order) }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light">
                                            <i class="ph ph-eye"></i> {{ __('View') }}
                                        </a>
                                        @if ($order->status === 'confirmed')
                                            <a href="{{ route('app.sales.orders.delivery-slip', $order) }}" target="_blank" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light">
                                                <i class="ph ph-printer"></i> {{ __('Delivery Slip') }}
                                            </a>
                                        @endif
                                        @if (! in_array($order->status, ['cancelled', 'done']))
                                            <form method="POST" action="{{ route('app.sales.orders.cancel', $order) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}');">
                                                @csrf
                                                <button type="submit" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-danger hover:bg-light">
                                                    <i class="ph ph-x-circle"></i> {{ __('Cancel') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-sm text-default">{{ __('No sales orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const search = document.getElementById('so-search');
    const rows = () => Array.from(document.querySelectorAll('[data-so-row]'));
    const activeStatuses = () => Array.from(document.querySelectorAll('[data-so-status-filter]:checked')).map(x => x.value);
    const apply = () => {
        const q = (search?.value || '').toLowerCase().trim();
        const st = activeStatuses();
        rows().forEach(r => {
            const okQ = !q || (r.dataset.search || '').includes(q);
            const okS = st.length === 0 || st.includes(r.dataset.status);
            r.style.display = (okQ && okS) ? '' : 'none';
        });
    };
    search?.addEventListener('input', apply);
    document.querySelectorAll('[data-so-status-filter]').forEach(el => el.addEventListener('change', apply));
})();
</script>
@endsection
