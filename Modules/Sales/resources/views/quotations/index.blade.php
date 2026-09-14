@extends('app.layouts.app')

@section('title', __('Quotations'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Quotations') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Draft and sent quotations. Convert to a sales order when the customer confirms.') }}</p>
    </div>
    <div class="flex items-center flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph-duotone ph-printer"></i> {{ __('Print') }}
        </button>
        <button type="button" data-hs-overlay="#add-quotation-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('Add New') }}
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
                <input type="text" id="q-search" class="w-full px-3 pe-8 py-2 h-7 text-[12px]! border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" placeholder="{{ __('Search') }}">
            </div>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-primary hover:border-primary hover:text-white focus:outline-hidden">
                    <i class="ph ph-funnel font-normal"></i> {{ __('Filter') }} <i class="ph ph-caret-down text-xs"></i>
                </button>
                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                    <div class="p-2 space-y-1">
                        @foreach (['draft', 'quotation_sent', 'cancelled'] as $s)
                            <label class="flex items-center gap-2 text-sm cursor-pointer px-2 py-1.5 rounded-md hover:bg-light">
                                <input type="checkbox" value="{{ $s }}" data-q-status-filter class="size-4 rounded border-border-color text-primary focus:ring-0">
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
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Number') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Created By') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-center py-2 px-2 font-semibold text-gray-900">{{ __('Lines') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $q)
                    @php $total = $q->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0'); @endphp
                    <tr class="border-b border-border-color hover:bg-light/50" data-q-row data-status="{{ $q->status }}" data-search="{{ strtolower($q->partner->name.' '.$q->creator?->name.' SO-'.str_pad((string) $q->id, 5, '0', STR_PAD_LEFT)) }}">
                        <td class="py-2.5 px-2 text-sm">
                            <a href="{{ route('app.sales.orders.show', $q) }}" class="font-mono text-default hover:text-primary">#SO{{ str_pad((string) $q->id, 5, '0', STR_PAD_LEFT) }}</a>
                        </td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $q->partner->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $q->creator?->name ?? '—' }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $q->created_at->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">{{ number_format((float) $total, 2) }}</td>
                        <td class="py-2.5 px-2 text-sm text-default text-center">{{ $q->lines->count() }}</td>
                        <td class="py-2.5 px-2">@include('sales::orders._status-badge', ['status' => $q->status])</td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm size-7 bg-white border border-border-color text-gray-600 inline-flex items-center justify-center hover:bg-light hover:text-gray-900 focus:outline-hidden">
                                    <i class="ph ph-dots-three-vertical"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-40 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                                    <div class="p-2 space-y-1">
                                        <a href="{{ route('app.sales.orders.show', $q) }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light">
                                            <i class="ph ph-eye"></i> {{ __('Open') }}
                                        </a>
                                        @if ($q->status === 'quotation_sent')
                                            <a href="{{ route('app.sales.orders.quotation', $q) }}" target="_blank" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light">
                                                <i class="ph ph-file-text"></i> {{ __('Preview') }}
                                            </a>
                                        @endif
                                        @if ($q->status !== 'cancelled')
                                            <form method="POST" action="{{ route('app.sales.orders.cancel', $q) }}" onsubmit="return confirm('{{ __('Cancel this sales order?') }}');">
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
                    <tr><td colspan="8" class="py-8 text-center text-sm text-default">{{ __('No quotations yet.') }}</td></tr>
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
                <p class="text-[11px] text-default mb-0">{{ __('A draft quotation will be created; add lines on the next page.') }}</p>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-quotation-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Create') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const search = document.getElementById('q-search');
    const rows = () => Array.from(document.querySelectorAll('[data-q-row]'));
    const activeStatuses = () => Array.from(document.querySelectorAll('[data-q-status-filter]:checked')).map(x => x.value);
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
    document.querySelectorAll('[data-q-status-filter]').forEach(el => el.addEventListener('change', apply));
})();
</script>
@endsection
