@extends('app.layouts.app')

@section('title', __('Products'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Products') }}</h1>
    <div class="flex items-center gap-2">
        <button type="button" data-hs-overlay="#add-category-modal" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph ph-tag"></i> {{ __('New Category') }}
        </button>
        <button type="button" data-hs-overlay="#add-product-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('New Product') }}
        </button>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">SKU</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Stock') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->sku ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->category?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->uom->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ __('product-type.'.$product->product_type) }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            @if ($product->product_type === 'service')
                                —
                            @else
                                {{ rtrim(rtrim($product->current_stock, '0'), '.') ?: '0' }}
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <button type="button" data-hs-overlay="#edit-product-modal-{{ $product->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                <i class="ph ph-pencil-simple-line"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No products yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::products._form-modal', ['id' => 'add-product-modal', 'action' => route('app.inventory.products.store'), 'method' => 'POST', 'title' => __('New Product'), 'product' => null])
@foreach ($products as $product)
    @include('inventory::products._form-modal', ['id' => 'edit-product-modal-'.$product->id, 'action' => route('app.inventory.products.update', $product), 'method' => 'PUT', 'title' => __('Edit Product'), 'product' => $product])
@endforeach

<div id="add-category-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.categories.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Category') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-category-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Category') }} <span class="text-danger">*</span></label>
                <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-category-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
