@extends('app.layouts.app')

@section('title', __('Partners'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Partners') }}</h1>
    <div class="flex items-center gap-2">
        <button type="button" data-hs-overlay="#add-partner-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('New Partner') }}
        </button>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tax Number') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Customer') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Supplier') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Payment Term (days)') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $partner->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $partner->tax_number ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm">
                            @if ($partner->is_customer)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Yes') }}</span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('No') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm">
                            @if ($partner->is_supplier)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Yes') }}</span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('No') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $partner->payment_term_days }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-partner-modal-{{ $partner->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No partners yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::partners._form-modal', ['id' => 'add-partner-modal', 'action' => route('app.inventory.partners.store'), 'method' => 'POST', 'title' => __('New Partner'), 'partner' => null])
@foreach ($partners as $partner)
    @include('inventory::partners._form-modal', ['id' => 'edit-partner-modal-'.$partner->id, 'action' => route('app.inventory.partners.update', $partner), 'method' => 'PUT', 'title' => __('Edit Partner'), 'partner' => $partner])
@endforeach
@endsection
