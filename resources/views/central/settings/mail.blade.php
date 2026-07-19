@extends('central.layouts.app')

@section('title', 'E-posta Ayarları')

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">Platform E-posta Ayarları</h1>
    <p class="text-sm text-default mb-0">Süper Admin bildirimlerinde (ör. tenant yöneticisi davetleri) kullanılacak SMTP sunucusu. Her tenant kendi SMTP ayarını tenant detay sayfasından tanımlayabilir; tanımlamazsa bu ayar kullanılır.</p>
</div>

<div class="bg-white border border-border-color rounded-md p-4 max-w-3xl">
    <form method="POST" action="{{ route('central.web.settings.mail.update') }}">
        @csrf
        @method('PUT')
        @include('central.settings._mail-form', ['setting' => $setting, 'formId' => 'platform'])
        <div class="mt-4">
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">Kaydet</button>
        </div>
    </form>
</div>
@endsection
