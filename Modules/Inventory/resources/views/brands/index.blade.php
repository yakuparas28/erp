@extends('app.layouts.app')

@section('title', __('Brands'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Brands') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Manage product brands used across inventory and sales.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-brand-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('Add New') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('brand')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Website') }}</th>
                    <th class="text-center py-2 px-2 font-semibold text-gray-900">{{ __('Products') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($brands as $brand)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-2.5 px-2 text-sm text-default font-mono">{{ $brand->code ?: '—' }}</td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $brand->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">
                            @php $safeUrl = \Illuminate\Support\Str::startsWith(strtolower((string) $brand->website), ['http://', 'https://']) ? $brand->website : null; @endphp
                            @if ($safeUrl)<a href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="hover:text-primary">{{ $brand->website }}</a>@else — @endif
                        </td>
                        <td class="py-2.5 px-2 text-sm text-default text-center">{{ $brand->products_count }}</td>
                        <td class="py-2.5 px-2 text-sm">
                            @if ($brand->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-right">
                            <div class="flex items-center gap-1 justify-end">
                                <button type="button" data-hs-overlay="#edit-brand-modal-{{ $brand->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.brands.destroy', $brand) }}" onsubmit="return confirm('{{ __('Delete?') }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No brands yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::brands._form-modal', ['id' => 'add-brand-modal', 'action' => route('app.inventory.brands.store'), 'method' => 'POST', 'title' => __('New Brand'), 'brand' => null])
@foreach ($brands as $brand)
    @include('inventory::brands._form-modal', ['id' => 'edit-brand-modal-'.$brand->id, 'action' => route('app.inventory.brands.update', $brand), 'method' => 'PUT', 'title' => __('Edit Brand'), 'brand' => $brand])
@endforeach
@endsection
