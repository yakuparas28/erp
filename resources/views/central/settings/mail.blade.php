@extends('central.layouts.app')

@section('title', __('Email Settings'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Platform Email Settings') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('SMTP server used for Super Admin notifications (e.g. tenant administrator invitations). Each tenant can define its own SMTP settings on the tenant detail page; otherwise this setting is used.') }}</p>
</div>

<div class="bg-white border border-border-color rounded-md p-4 max-w-3xl">
    <form method="POST" action="{{ route('central.web.settings.mail.update') }}">
        @csrf
        @method('PUT')
        @include('central.settings._mail-form', ['setting' => $setting, 'formId' => 'platform'])
        <div class="mt-4">
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
        </div>
    </form>
</div>
@endsection
