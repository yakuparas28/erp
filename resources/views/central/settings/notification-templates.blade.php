@extends('central.layouts.app')

@section('title', __('Notification Templates'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Notification Templates') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Edit email subjects and bodies here. Markdown is supported;') }} <code>@{{degisken}}</code> {{ __('placeholders are replaced with real values on send.') }}</p>
</div>

@foreach ($templates as $template)
    <div class="bg-white border border-border-color rounded-md p-4 mb-4 max-w-3xl">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-title mb-0">{{ $template->name }}</h2>
            <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ $template->key }}</span>
        </div>
        <form method="POST" action="{{ route('central.web.settings.templates.update', $template) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Subject') }}</label>
                <input type="text" name="subject" required value="{{ $template->subject }}"
                       class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Body (Markdown)') }}</label>
                <textarea name="body" rows="10" required
                          class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">{{ $template->body }}</textarea>
                @if ($template->key === 'tenant_admin_invitation')
                    <p class="text-[11px] text-default mt-1 mb-0">{{ __('Available variables:') }}
                        <code>@{{yonetici_adi}}</code>, <code>@{{yonetici_email}}</code>, <code>@{{firma_adi}}</code>, <code>@{{gecici_sifre}}</code>, <code>@{{uygulama_adi}}</code>
                    </p>
                @endif
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save Template') }}</button>
        </form>
    </div>
@endforeach
@endsection
