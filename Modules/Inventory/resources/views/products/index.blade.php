@extends('app.layouts.app')

@section('title', __('Products'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Products') }}</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.inventory.templates.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
            <i class="ph ph-stack"></i> {{ __('Variant Templates') }}
        </a>
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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Barcode') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('List Price') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Stock') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($standaloneProducts as $product)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">
                            <div class="flex items-center gap-2">
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/'.$product->image_path) }}" alt="" class="size-8 rounded border border-border-color object-cover">
                                @endif
                                <span>{{ $product->name }}</span>
                            </div>
                            @if ($product->is_kit)
                                <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded ms-1">{{ __('Kit') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default font-mono">{{ $product->sku ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default font-mono">{{ $product->barcode ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->category?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->uom->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $product->list_price ?? '0' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            @if ($product->product_type === 'service')
                                —
                            @else
                                {{ rtrim(rtrim($product->current_stock, '0'), '.') ?: '0' }}
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-product-modal-{{ $product->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple-line"></i>
                                </button>
                                @if ($product->is_kit)
                                    <button type="button" data-hs-overlay="#kit-components-modal-{{ $product->id }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-1 hover:bg-light cursor-pointer">
                                        <i class="ph ph-stack-simple"></i> {{ __('Components') }}
                                    </button>
                                @endif
                                <form method="POST" action="{{ route('app.inventory.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Delete this product?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                @endforelse

                @forelse ($templates as $template)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">
                            <a href="{{ route('app.inventory.templates.show', $template) }}" class="hover:underline">{{ $template->name }}</a>
                            <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded ms-1">{{ __('Variants') }}: {{ $template->variants_count }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">—</td>
                        <td class="py-2.5 px-3 text-sm text-default">—</td>
                        <td class="py-2.5 px-3 text-sm text-default">—</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $template->variants->first()?->uom?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $template->base_price ?? '0' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ rtrim(rtrim((string) $template->variants->sum('current_stock'), '0'), '.') ?: '0' }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('app.inventory.templates.show', $template) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                    {{ __('View Variants') }}
                                </a>
                                <form method="POST" action="{{ route('app.inventory.templates.destroy', $template) }}" onsubmit="return confirm('{{ __('Delete this template and all its variants?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                @endforelse

                @if ($standaloneProducts->isEmpty() && $templates->isEmpty())
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No products yet.') }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@include('inventory::products._form-modal', ['id' => 'add-product-modal', 'action' => route('app.inventory.products.store'), 'method' => 'POST', 'title' => __('New Product'), 'product' => null])
@foreach ($standaloneProducts as $product)
    @include('inventory::products._form-modal', ['id' => 'edit-product-modal-'.$product->id, 'action' => route('app.inventory.products.update', $product), 'method' => 'PUT', 'title' => __('Edit Product'), 'product' => $product])
    @if ($product->is_kit)
        @include('inventory::products._kit-components-modal', ['product' => $product, 'nonKitProducts' => $nonKitProducts])
    @endif
@endforeach

@endsection
