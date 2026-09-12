@extends('app.layouts.app')

@section('title', __('Storage Categories'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Storage Categories') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Storage constraints for locations: maximum weight and how new products are accepted.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-storage-category-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Storage Category') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('storage_category')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Max Weight') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Allow New Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Locations') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900 w-32">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $category->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $category->max_weight }} kg</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] bg-default-transparent text-default px-2 py-0.5 rounded">{{ __('storage-policy.'.$category->allow_new_product) }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $category->locations_count }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-storage-category-modal-{{ $category->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.storage-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete this storage category?') }}')">
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
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No storage categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::storage-categories._form-modal', ['id' => 'add-storage-category-modal', 'action' => route('app.inventory.storage-categories.store'), 'title' => __('New Storage Category'), 'storageCategory' => null])

@foreach ($categories as $category)
    @include('inventory::storage-categories._form-modal', ['id' => 'edit-storage-category-modal-'.$category->id, 'action' => route('app.inventory.storage-categories.update', $category), 'title' => __('Edit Storage Category'), 'storageCategory' => $category, 'method' => 'PATCH'])
@endforeach
@endsection
