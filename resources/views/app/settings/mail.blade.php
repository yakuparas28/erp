@extends('app.layouts.app')

@section('title', 'E-posta Ayarları')

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">E-posta Ayarları</h1>
    <p class="text-sm text-default mb-0">Firmanızın bildirimleri bu SMTP sunucusundan gönderilir. Tanımlamazsanız platform varsayılanı kullanılır.</p>
</div>

<div class="bg-white border border-border-color rounded-md p-4 max-w-3xl">
    <form method="POST" action="{{ route('app.settings.mail.update') }}">
        @csrf
        @method('PUT')
        @include('central.settings._mail-form', ['setting' => $setting, 'formId' => 'tenant-own'])
        <div class="mt-4">
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">Kaydet</button>
        </div>
    </form>
</div>
@endsection
