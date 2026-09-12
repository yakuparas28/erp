@extends('app.layouts.app')

@section('title', __('Delivery Carriers'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Delivery Carriers') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Shipping companies with tracking URL templates. Use {tracking_number} as a placeholder.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-carrier-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Carrier') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tracking URL') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($carriers as $carrier)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $carrier->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default font-mono">{{ $carrier->code ?: '—' }}</td>
                        <td class="py-2.5 px-3 text-xs text-default font-mono">{{ $carrier->tracking_url_template ?: '—' }}</td>
                        <td class="py-2.5 px-3">
                            @if ($carrier->active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-default-transparent text-default px-2 py-0.5 rounded">{{ __('Archived') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-carrier-modal-{{ $carrier->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.sales.carriers.destroy', $carrier) }}" onsubmit="return confirm('{{ __('Delete this carrier?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No carriers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('sales::carriers._form-modal', ['id' => 'add-carrier-modal', 'action' => route('app.sales.carriers.store'), 'title' => __('New Carrier'), 'carrier' => null])

@foreach ($carriers as $c)
    @include('sales::carriers._form-modal', ['id' => 'edit-carrier-modal-'.$c->id, 'action' => route('app.sales.carriers.update', $c), 'title' => __('Edit Carrier'), 'carrier' => $c, 'method' => 'PATCH'])
@endforeach
@endsection
