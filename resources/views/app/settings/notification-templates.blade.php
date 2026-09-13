@extends('app.layouts.app')

@section('title', __('Notification Templates'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Notification Templates') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Editing a template creates a copy specific to your company; the platform default is not affected. Markdown is supported,') }} <code>@{{degisken}}</code> {{ __('placeholders are filled on send.') }}</p>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">

    <aside class="bg-white border border-border-color rounded-md lg:sticky lg:top-4">
        <div class="p-3 border-b border-border-color">
            <h2 class="text-sm font-semibold text-title mb-0">{{ __('Templates') }} <span class="text-xs text-default font-normal">({{ $templates->count() }})</span></h2>
        </div>
        <nav class="p-2 max-h-[70vh] overflow-y-auto">
            @foreach ($templates as $item)
                <a href="#tpl-{{ $item['default']->key }}" class="flex items-start justify-between gap-2 px-2 py-2 rounded-md hover:bg-light text-sm">
                    <span class="text-title font-medium leading-tight">{{ $item['default']->name }}</span>
                    @if ($item['override'])
                        <span class="text-[10px] bg-info-transparent text-info px-1.5 py-0.5 rounded shrink-0">{{ __('Custom') }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="lg:col-span-3 space-y-3">
        @foreach ($templates as $item)
            @php($effective = $item['override'] ?? $item['default'])
            <div id="tpl-{{ $item['default']->key }}" class="hs-accordion bg-white border border-border-color rounded-md scroll-mt-4" data-hs-accordion="">
                <button type="button" class="hs-accordion-toggle w-full flex items-center justify-between gap-3 p-4 text-left cursor-pointer hover:bg-light">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-title mb-0.5 truncate">{{ $item['default']->name }}</h2>
                        <p class="text-xs text-default mb-0 font-mono truncate">{{ $item['default']->key }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($item['override'])
                            <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded">{{ __('Custom for your company') }}</span>
                        @else
                            <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded border border-border-color">{{ __('Platform default') }}</span>
                        @endif
                        <i class="ph ph-caret-down text-default hs-accordion-active:rotate-180 transition-transform"></i>
                    </div>
                </button>
                <div class="hs-accordion-content hidden overflow-hidden transition-[height] duration-300">
                    <form method="POST" action="{{ route('app.settings.templates.update', $item['default']->key) }}" class="p-4 border-t border-border-color space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Subject') }}</label>
                            <input type="text" name="subject" required value="{{ $effective->subject }}"
                                   class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Body (Markdown)') }}</label>
                            <textarea name="body" rows="12" required
                                      class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">{{ $effective->body }}</textarea>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-border-color">
                            @if ($item['override'])
                                <button type="submit" form="reset-{{ $item['default']->key }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light inline-flex items-center gap-2">
                                    <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Reset to platform default') }}
                                </button>
                            @else
                                <span></span>
                            @endif
                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover inline-flex items-center gap-2">
                                <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
                            </button>
                        </div>
                    </form>
                    @if ($item['override'])
                        <form id="reset-{{ $item['default']->key }}" method="POST" action="{{ route('app.settings.templates.reset', $item['default']->key) }}" onsubmit="return confirm('{{ __('Reset this template to the platform default?') }}')" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection
