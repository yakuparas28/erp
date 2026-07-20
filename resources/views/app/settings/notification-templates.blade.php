@extends('app.layouts.app')

@section('title', __('Notification Templates'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Notification Templates') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Editing a template creates a copy specific to your company; the platform default is not affected. Markdown is supported,') }} <code>@{{degisken}}</code> {{ __('placeholders are filled on send.') }}</p>
</div>

@foreach ($templates as $item)
    @php($effective = $item['override'] ?? $item['default'])
    <div class="bg-white border border-border-color rounded-md p-4 mb-4 max-w-3xl">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-title mb-0">{{ $item['default']->name }}</h2>
            @if ($item['override'])
                <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded">{{ __('Custom for your company') }}</span>
            @else
                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('Platform default') }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('app.settings.templates.update', $item['default']->key) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Subject') }}</label>
                <input type="text" name="subject" required value="{{ $effective->subject }}"
                       class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Body (Markdown)') }}</label>
                <textarea name="body" rows="10" required
                          class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">{{ $effective->body }}</textarea>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
        @if ($item['override'])
            <form method="POST" action="{{ route('app.settings.templates.reset', $item['default']->key) }}" class="mt-2">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Reset to platform default') }}</button>
            </form>
        @endif
    </div>
@endforeach
@endsection
