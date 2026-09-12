@extends('app.layouts.app')

@section('title', __('Package Types'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Package Types') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Shipping containers (boxes, pallets) with their dimensions and max weight. Used later for pack operations.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-package-type-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Package Type') }}
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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Barcode') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Dimensions (L × W × H)') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Max Weight') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900 w-32">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packageTypes as $type)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $type->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default font-mono">{{ $type->barcode ?: '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $type->packaging_length }} × {{ $type->width }} × {{ $type->height }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $type->max_weight }} kg</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-package-type-modal-{{ $type->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.package-types.destroy', $type) }}" onsubmit="return confirm('{{ __('Delete this package type?') }}')">
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
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No package types yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::package-types._form-modal', ['id' => 'add-package-type-modal', 'action' => route('app.inventory.package-types.store'), 'title' => __('New Package Type'), 'packageType' => null])

@foreach ($packageTypes as $type)
    @include('inventory::package-types._form-modal', ['id' => 'edit-package-type-modal-'.$type->id, 'action' => route('app.inventory.package-types.update', $type), 'title' => __('Edit Package Type'), 'packageType' => $type, 'method' => 'PATCH'])
@endforeach
@endsection
