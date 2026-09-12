@extends('app.layouts.app')

@section('title', __('Units of Measure'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Units of Measure') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Every category has one reference unit (factor = 1). Other units convert to the reference by their factor.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-uom-category-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Unit Category') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('category')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror
@error('uom')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror
@error('is_reference')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="space-y-3">
    @forelse ($categories as $category)
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-bold text-title mb-0">{{ $category->name }}</h2>
                <div class="flex items-center gap-2">
                    <button type="button" data-hs-overlay="#add-uom-modal-{{ $category->id }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-1 hover:bg-light cursor-pointer">
                        <i class="ph ph-plus text-xs"></i> {{ __('New Unit') }}
                    </button>
                    <form method="POST" action="{{ route('app.inventory.uom-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete this category?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                            <i class="ph ph-trash text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Factor') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900 w-32">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($category->uoms->sortByDesc('is_reference')->sortBy('name') as $uom)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $uom->name }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $uom->factor }}</td>
                                <td class="py-2.5 px-3">
                                    @if ($uom->is_reference)
                                        <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded">{{ __('Reference') }}</span>
                                    @else
                                        <span class="text-[11px] bg-default-transparent text-default px-2 py-0.5 rounded">{{ __('Converted') }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" data-hs-overlay="#edit-uom-modal-{{ $uom->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                            <i class="ph ph-pencil-simple text-xs"></i>
                                        </button>
                                        @unless ($uom->is_reference)
                                            <form method="POST" action="{{ route('app.inventory.uoms.destroy', $uom) }}" onsubmit="return confirm('{{ __('Delete this unit?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                                    <i class="ph ph-trash text-xs"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No unit categories yet.') }}</div>
    @endforelse
</div>

<div id="add-uom-category-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.uom-categories.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Unit Category') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-uom-category-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('e.g. Ağırlık') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-uom-category-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@foreach ($categories as $category)
    <div id="add-uom-modal-{{ $category->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.inventory.uoms.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                <input type="hidden" name="uom_category_id" value="{{ $category->id }}">
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('New Unit in') }} {{ $category->name }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-uom-modal-{{ $category->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Factor') }} <span class="text-danger">*</span></label>
                        <input type="number" name="factor" required step="0.000001" min="0.000001" value="1" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    @if (! $category->uoms->contains('is_reference', true))
                        <div class="col-span-12">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_reference" value="1" class="rounded border-border-color">
                                <span>{{ __('This is the reference unit (factor 1)') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-uom-modal-{{ $category->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
    @foreach ($category->uoms as $uom)
        <div id="edit-uom-modal-{{ $uom->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
            <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
                <form method="POST" action="{{ route('app.inventory.uoms.update', $uom) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                    @csrf
                    @method('PATCH')
                    <div class="flex justify-between items-center p-4 border-b border-border-color">
                        <h2 class="text-base font-bold text-title">{{ __('Edit') }} — {{ $uom->name }}</h2>
                        <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-uom-modal-{{ $uom->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                    </div>
                    <div class="p-4 grid grid-cols-12 gap-3">
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" required value="{{ $uom->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Factor') }} <span class="text-danger">*</span></label>
                            <input type="number" name="factor" required step="0.000001" min="0.000001" value="{{ $uom->factor }}" @disabled($uom->is_reference) class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                        <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-uom-modal-{{ $uom->id }}">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endforeach
@endsection
