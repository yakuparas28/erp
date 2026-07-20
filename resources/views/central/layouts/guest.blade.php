<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Yönetim Paneli') | ERP Merkez</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('template/v1/assets/img/favicon.png') }}">
    <script src="{{ asset('template/v1/assets/js/theme-script.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/duotone/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/fill/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/lucide-static/font/lucide.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/css/style.css') }}">
</head>
<body class="bg-light min-h-screen flex items-center justify-center p-4">
    <div class="fixed top-4 end-4 hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
        <button type="button" class="hs-dropdown-toggle size-9 bg-white border border-border-color rounded-md flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" aria-label="{{ __('Language') }}">
            <i class="ph-duotone ph-translate"></i>
        </button>
        <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-30 p-3 bg-white border border-border-color shadow rounded-md z-10 space-y-1" role="menu" aria-orientation="vertical">
            @foreach (['tr' => ['turkey.svg', 'TUR'], 'en' => ['us.svg', 'ENG']] as $code => [$flag, $label])
                <form method="POST" action="{{ route('locale.update', $code) }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2 px-2 py-[6px] rounded-md text-gray-900 hover:bg-light focus:outline-hidden cursor-pointer {{ app()->getLocale() === $code ? 'bg-light font-semibold' : '' }}">
                        <img src="{{ asset('template/v1/assets/img/icons/'.$flag) }}" class="size-4 rounded-full" alt="flag">{{ $label }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
    @yield('content')

    <script src="{{ asset('template/v1/assets/libs/preline/preline.js') }}"></script>
</body>
</html>
