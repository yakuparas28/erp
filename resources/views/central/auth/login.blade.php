@extends('central.layouts.guest')

@section('title', 'Giriş')

@section('content')
<div class="bg-white border border-border-color rounded-md shadow-sm w-full max-w-md sm:p-8 p-5">
    <div class="text-center mb-6">
        <h1 class="text-xl font-bold text-title mb-1">Yönetim Paneline Giriş</h1>
        <p class="text-sm text-default mb-0">Süper Admin hesabınızla oturum açın</p>
    </div>
    <form class="space-y-4" method="POST" action="{{ route('central.web.login.store') }}">
        @csrf
        <div>
            <label for="email" class="text-sm font-semibold text-gray-900 mb-1 block">E-posta Adresi</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="w-full px-3 py-2.5 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0"
                   placeholder="admin@ornek.com">
            @error('email')
                <p class="text-sm text-danger mt-1 mb-0">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="text-sm text-gray-900 mb-1 block">Şifre</label>
            <div class="relative">
                <input id="password" name="password" type="password" required
                       class="form-input form-input-icon w-full px-3 py-2.5 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:border-border-color focus:ring-0 h-10"
                       placeholder="************">
                <span class="absolute start-0 top-2 ms-3"><i class="icon icon-lock"></i></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" class="size-4 rounded border-border-color" id="remember" name="remember" value="1">
            <label for="remember" class="text-sm text-default">Beni hatırla</label>
        </div>
        <button type="submit" class="w-full bg-dark text-white py-2.5 rounded-md text-sm font-semibold hover:bg-primary-hover cursor-pointer">Giriş Yap</button>
    </form>
</div>
@endsection
