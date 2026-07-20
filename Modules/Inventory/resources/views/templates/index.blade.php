@extends('app.layouts.app')

@section('title', __('Variant Templates'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Variant Templates') }}</h1>
    <div class="flex items-center gap-2">
        <button type="button" data-hs-overlay="#add-attribute-modal" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph ph-tag"></i> {{ __('New Attribute') }}
        </button>
        <button type="button" data-hs-overlay="#add-template-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
            <i class="ph ph-plus"></i> {{ __('New Template') }}
        </button>
    </div>
</div>

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-7">
        <div class="bg-white border border-border-color rounded-md">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Base Price') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Variants') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $template->name }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $template->base_price }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $template->variants_count }}</td>
                                <td class="py-2.5 px-3">
                                    <a href="{{ route('app.inventory.templates.show', $template) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                        {{ __('Open') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-sm text-default">{{ __('No templates yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-5">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Attributes') }}</h2>
            <ul class="space-y-3">
                @forelse ($attributes as $attribute)
                    <li class="border border-border-color rounded-md p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-title">{{ $attribute->name }}</span>
                            <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('creation-mode.'.$attribute->creation_mode) }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-2">
                            @foreach ($attribute->values as $value)
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ $value->value }} @if ((float) $value->price_extra !== 0.0) (+{{ $value->price_extra }}) @endif</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('app.inventory.attributes.values.store', $attribute) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="text" name="value" required placeholder="{{ __('New value') }}" class="flex-1 min-w-24 px-2 py-1.5 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <input type="number" step="0.0001" name="price_extra" placeholder="+₺" class="w-20 px-2 py-1.5 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Add') }}</button>
                        </form>
                    </li>
                @empty
                    <li class="text-sm text-default">{{ __('No attributes yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div id="add-template-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.templates.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Template') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-template-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Base Price') }} <span class="text-danger">*</span></label>
                    <input type="number" step="0.0001" min="0" name="base_price" required value="0" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-template-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="add-attribute-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.attributes.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Attribute') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-attribute-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required placeholder="{{ __('e.g. Color') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Creation Mode') }}</label>
                    <select name="creation_mode" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="instant">{{ __('creation-mode.instant') }}</option>
                        <option value="dynamic">{{ __('creation-mode.dynamic') }}</option>
                        <option value="never">{{ __('creation-mode.never') }}</option>
                    </select>
                    <p class="text-[11px] text-default mt-1 mb-0">{{ __('This cannot be changed once the attribute is attached to a template.') }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-attribute-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
