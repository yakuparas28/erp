@extends('app.layouts.app')

@section('title', __('Email Settings'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Email Settings') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Your company notifications are sent from this SMTP server. If not defined, the platform default is used.') }}</p>
</div>

<div class="bg-white border border-border-color rounded-md p-4 max-w-3xl">
    <form method="POST" action="{{ route('app.settings.mail.update') }}">
        @csrf
        @method('PUT')
        @include('central.settings._mail-form', ['setting' => $setting, 'formId' => 'tenant-own'])
        <div class="mt-4">
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
        </div>
    </form>
</div>
@endsection
