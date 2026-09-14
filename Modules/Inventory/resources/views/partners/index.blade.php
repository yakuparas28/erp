@extends('app.layouts.app')

@section('title', __('Contacts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Contacts') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Customers and suppliers used across sales, purchase and accounting.') }}</p>
    </div>
    <div class="flex items-center flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph-duotone ph-printer"></i> {{ __('Print') }}
        </button>
        <button type="button" data-hs-overlay="#add-partner-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
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
                <input type="text" id="p-search" class="w-full px-3 pe-8 py-2 h-7 text-[12px]! border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" placeholder="{{ __('Search') }}">
            </div>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-primary hover:border-primary hover:text-white focus:outline-hidden">
                    <i class="ph ph-funnel font-normal"></i> {{ __('Filter') }} <i class="ph ph-caret-down text-xs"></i>
                </button>
                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                    <div class="p-2 space-y-1">
                        <label class="flex items-center gap-2 text-sm cursor-pointer px-2 py-1.5 rounded-md hover:bg-light">
                            <input type="checkbox" value="customer" data-p-role-filter class="size-4 rounded border-border-color text-primary focus:ring-0">
                            {{ __('Customer') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer px-2 py-1.5 rounded-md hover:bg-light">
                            <input type="checkbox" value="supplier" data-p-role-filter class="size-4 rounded border-border-color text-primary focus:ring-0">
                            {{ __('Supplier') }}
                        </label>
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
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Tax Number') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('City') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Role') }}</th>
                    <th class="text-center py-2 px-2 font-semibold text-gray-900">{{ __('Payment Term (days)') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Credit Limit') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    @php
                        $roles = [];
                        if ($partner->is_customer) { $roles[] = 'customer'; }
                        if ($partner->is_supplier) { $roles[] = 'supplier'; }
                    @endphp
                    <tr class="border-b border-border-color hover:bg-light/50" data-p-row data-roles="{{ implode(' ', $roles) }}" data-search="{{ strtolower(($partner->partner_code ?? '').' '.$partner->name.' '.$partner->tax_number.' '.($partner->city ?? '')) }}">
                        <td class="py-2.5 px-2 text-sm text-default font-mono">{{ $partner->partner_code ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">
                            {{ $partner->name }}
                            @if ($partner->e_invoice_status !== 'none' && $partner->e_invoice_status !== null)
                                <span class="text-[9px] bg-primary-transparent text-primary px-1 py-0.5 rounded ms-1 uppercase">{{ str_replace('e_', 'e-', $partner->e_invoice_status) }}</span>
                            @endif
                            @if (! $partner->is_active)
                                <span class="text-[9px] bg-light text-default px-1 py-0.5 rounded ms-1">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-sm text-default font-mono">{{ $partner->tax_number ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $partner->city ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm">
                            <div class="flex items-center gap-1 flex-wrap">
                                @if ($partner->is_customer)
                                    <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded inline-flex items-center gap-1"><i class="ph ph-user"></i> {{ __('Customer') }}</span>
                                @endif
                                @if ($partner->is_supplier)
                                    <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded inline-flex items-center gap-1"><i class="ph ph-truck"></i> {{ __('Supplier') }}</span>
                                @endif
                                @if (! $partner->is_customer && ! $partner->is_supplier)
                                    <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-2.5 px-2 text-sm text-default text-center">{{ $partner->payment_term_days }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">
                            @if ((float) $partner->credit_limit > 0)
                                {{ number_format((float) $partner->credit_limit, 2) }} <span class="text-[10px] text-default">{{ $partner->currency_code }}</span>
                            @else
                                <span class="text-default">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm size-7 bg-white border border-border-color text-gray-600 inline-flex items-center justify-center hover:bg-light hover:text-gray-900 focus:outline-hidden">
                                    <i class="ph ph-dots-three-vertical"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-40 bg-white border border-border-color shadow rounded-md mt-2 z-1" role="menu">
                                    <div class="p-2 space-y-1">
                                        <button type="button" data-hs-overlay="#edit-partner-modal-{{ $partner->id }}" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light">
                                            <i class="ph ph-pencil-simple-line"></i> {{ __('Edit') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-sm text-default">{{ __('No partners yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::partners._form-modal', ['id' => 'add-partner-modal', 'action' => route('app.inventory.partners.store'), 'method' => 'POST', 'title' => __('New Partner'), 'partner' => null])
@foreach ($partners as $partner)
    @include('inventory::partners._form-modal', ['id' => 'edit-partner-modal-'.$partner->id, 'action' => route('app.inventory.partners.update', $partner), 'method' => 'PUT', 'title' => __('Edit Partner'), 'partner' => $partner])
@endforeach

<script>
(function () {
    const search = document.getElementById('p-search');
    const rows = () => Array.from(document.querySelectorAll('[data-p-row]'));
    const activeRoles = () => Array.from(document.querySelectorAll('[data-p-role-filter]:checked')).map(x => x.value);
    const apply = () => {
        const q = (search?.value || '').toLowerCase().trim();
        const roles = activeRoles();
        rows().forEach(r => {
            const okQ = !q || (r.dataset.search || '').includes(q);
            const rowRoles = (r.dataset.roles || '').split(' ').filter(Boolean);
            const okR = roles.length === 0 || roles.some(x => rowRoles.includes(x));
            r.style.display = (okQ && okR) ? '' : 'none';
        });
    };
    search?.addEventListener('input', apply);
    document.querySelectorAll('[data-p-role-filter]').forEach(el => el.addEventListener('change', apply));
})();
</script>
@endsection
