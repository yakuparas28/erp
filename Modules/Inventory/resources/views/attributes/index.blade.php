@extends('app.layouts.app')

@section('title', __('Attributes'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Attributes') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Attributes (e.g. Color, Size) drive product variants. Instant creates all combinations on assignment; dynamic creates variants lazily.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-attribute-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Attribute') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('attribute')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror
@error('value')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="space-y-3">
    @forelse ($attributes as $attribute)
        <div class="bg-white border border-border-color rounded-md {{ $attribute->active ? '' : 'opacity-60' }}">
            <div class="p-4 border-b border-border-color flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex flex-col">
                        <form method="POST" action="{{ route('app.inventory.attributes.reorder', ['attribute' => $attribute, 'direction' => 'up']) }}">
                            @csrf
                            <button type="submit" class="size-4 text-default hover:text-title" title="{{ __('Move up') }}"><i class="ph ph-caret-up text-xs"></i></button>
                        </form>
                        <form method="POST" action="{{ route('app.inventory.attributes.reorder', ['attribute' => $attribute, 'direction' => 'down']) }}">
                            @csrf
                            <button type="submit" class="size-4 text-default hover:text-title" title="{{ __('Move down') }}"><i class="ph ph-caret-down text-xs"></i></button>
                        </form>
                    </div>
                    <h2 class="text-base font-bold text-title mb-0">{{ $attribute->name }}</h2>
                    <span class="text-[11px] {{ $attribute->creation_mode === 'instant' ? 'bg-info-transparent text-info' : ($attribute->creation_mode === 'dynamic' ? 'bg-warning-transparent text-warning' : 'bg-default-transparent text-default') }} px-2 py-0.5 rounded">{{ __('attribute-mode.'.$attribute->creation_mode) }}</span>
                    <span class="text-[11px] bg-light border border-border-color px-2 py-0.5 rounded">{{ __('display-type.'.$attribute->display_type) }}</span>
                    @unless ($attribute->active)
                        <span class="text-[11px] bg-default-transparent text-default px-2 py-0.5 rounded">{{ __('Archived') }}</span>
                    @endunless
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-hs-overlay="#edit-attribute-modal-{{ $attribute->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                        <i class="ph ph-pencil-simple text-xs"></i>
                    </button>
                    @if ($attribute->active)
                        <form method="POST" action="{{ route('app.inventory.attributes.archive', $attribute) }}" class="inline">
                            @csrf
                            <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-warning hover:bg-light cursor-pointer" title="{{ __('Archive') }}">
                                <i class="ph ph-archive text-xs"></i>
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('app.inventory.attributes.restore', $attribute) }}" class="inline">
                            @csrf
                            <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-success hover:bg-light cursor-pointer" title="{{ __('Restore') }}">
                                <i class="ph ph-arrow-counter-clockwise text-xs"></i>
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('app.inventory.attributes.destroy', $attribute) }}" onsubmit="return confirm('{{ __('Delete this attribute?') }}')" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                            <i class="ph ph-trash text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="p-4">
                <div class="flex flex-wrap gap-2 mb-3">
                    @forelse ($attribute->values as $value)
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-light border border-border-color text-sm">
                            <form method="POST" action="{{ route('app.inventory.attribute-values.reorder', ['value' => $value, 'direction' => 'up']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-default hover:text-title" title="{{ __('Move up') }}"><i class="ph ph-caret-left text-xs"></i></button>
                            </form>
                            @if ($attribute->display_type === 'color' && $value->html_color)
                                <span class="size-4 rounded-full border border-border-color" style="background: {{ $value->html_color }}"></span>
                            @endif
                            @if ($value->image_path)
                                <img src="{{ asset('storage/'.$value->image_path) }}" alt="" class="size-5 rounded object-cover">
                            @endif
                            {{ $value->value }}
                            @if ((float) $value->price_extra !== 0.0)
                                <span class="text-xs text-default">(+{{ $value->price_extra }})</span>
                            @endif
                            @if ($value->is_custom)
                                <span class="text-[10px] bg-info-transparent text-info px-1 rounded">{{ __('custom') }}</span>
                            @endif
                            <form method="POST" action="{{ route('app.inventory.attribute-values.reorder', ['value' => $value, 'direction' => 'down']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-default hover:text-title" title="{{ __('Move down') }}"><i class="ph ph-caret-right text-xs"></i></button>
                            </form>
                            <button type="button" data-hs-overlay="#edit-value-modal-{{ $value->id }}" class="text-default hover:text-title" title="{{ __('Edit') }}">
                                <i class="ph ph-pencil-simple text-xs"></i>
                            </button>
                            <form method="POST" action="{{ route('app.inventory.attribute-values.destroy', $value) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:text-danger-hover" title="{{ __('Delete') }}">×</button>
                            </form>
                        </span>
                    @empty
                        <span class="text-sm text-default">{{ __('No values yet.') }}</span>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('app.inventory.attributes.values.store', $attribute) }}" class="grid grid-cols-12 gap-2 items-end">
                    @csrf
                    <div class="col-span-12 sm:col-span-3">
                        <label class="text-xs text-default mb-1 block">{{ __('New value') }} <span class="text-danger">*</span></label>
                        <input type="text" name="value" required placeholder="{{ __('e.g. Kırmızı') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <label class="text-xs text-default mb-1 block">{{ __('Price Extra') }}</label>
                        <input type="number" name="price_extra" step="0.0001" placeholder="0" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    @if ($attribute->display_type === 'color')
                        <div class="col-span-6 sm:col-span-2">
                            <label class="text-xs text-default mb-1 block">{{ __('Color') }}</label>
                            <input type="color" name="html_color" value="#000000" class="w-full h-9 border border-border-color rounded-md cursor-pointer">
                        </div>
                    @endif
                    <div class="col-span-6 sm:col-span-1">
                        <label class="text-xs text-default mb-1 block">{{ __('Seq.') }}</label>
                        <input type="number" name="sequence" step="1" min="0" value="0" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-6 sm:col-span-2 flex items-center gap-2 pt-4">
                        <label class="inline-flex items-center gap-1 text-xs">
                            <input type="checkbox" name="is_custom" value="1" class="rounded border-border-color">
                            <span>{{ __('Custom') }}</span>
                        </label>
                    </div>
                    <div class="col-span-12 sm:col-span-2">
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer w-full">{{ __('Add') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No attributes yet.') }}</div>
    @endforelse
</div>

@include('inventory::attributes._form-modal', ['id' => 'add-attribute-modal', 'action' => route('app.inventory.attributes.store'), 'title' => __('New Attribute'), 'attribute' => null])

@foreach ($attributes as $attribute)
    @include('inventory::attributes._form-modal', ['id' => 'edit-attribute-modal-'.$attribute->id, 'action' => route('app.inventory.attributes.update', $attribute), 'title' => __('Edit Attribute'), 'attribute' => $attribute, 'method' => 'PATCH'])
    @foreach ($attribute->values as $value)
        @include('inventory::attributes._value-edit-modal', ['value' => $value, 'attribute' => $attribute])
    @endforeach
@endforeach
@endsection
