<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Kontrol Paneli') | {{ auth()->user()?->tenant?->name ?? 'ERP' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('template/v1/assets/img/favicon.png') }}">
    <script src="{{ asset('template/v1/assets/js/theme-script.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/duotone/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/fill/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/lucide-static/font/lucide.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/simplebar/simplebar.min.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/css/style.css') }}">
</head>
<body>
    <div class="main-wrapper">
        <header class="navbar-header flex items-center max-lg:w-full">
            <div class="topbar-menu flex items-center justify-between w-full gap-2">
                <div class="flex items-center gap-3">
                    <a id="mobile_btn" class="mobile-btn lg:hidden" href="#sidebar" aria-label="menu">
                        <i class="icon icon-menu"></i>
                    </a>
                    <a href="{{ route('app.dashboard') }}" class="logo">
                        <span class="logo-light">
                            <span class="logo-lg"><img src="{{ asset('template/v1/assets/img/logo.svg') }}" alt="logo"></span>
                            <span class="logo-sm"><img src="{{ asset('template/v1/assets/img/logo-small.svg') }}" alt="logo"></span>
                        </span>
                        <span class="logo-dark">
                            <span class="logo-lg"><img src="{{ asset('template/v1/assets/img/logo-white.svg') }}" alt="logo"></span>
                        </span>
                    </a>
                    <button class="sidenav-toggle-btn topbar-link shrink-0 size-9 text-[20px] items-center justify-center rounded-full" id="toggle_btn2" aria-label="toggle">
                        <i class="ph-duotone ph-arrow-left"></i>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="header-item">
                        <button class="topbar-link items-center justify-center light-dark-mode" type="button" aria-label="tema">
                            <i class="ph-duotone ph-moon"></i>
                        </button>
                    </div>
                    <div class="header-item hs-dropdown [--placement:bottom-right] relative inline-flex">
                        <button type="button" class="hs-dropdown-toggle topbar-link items-center justify-center" aria-label="hesap">
                            <i class="ph-duotone ph-user-circle text-[22px]"></i>
                        </button>
                        <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-10 p-2 space-y-1" role="menu">
                            <p class="px-2 py-1.5 text-sm font-semibold text-title mb-0">{{ auth()->user()?->name }}</p>
                            <p class="px-2 text-[11px] text-default mb-1">{{ auth()->user()?->tenant?->name }}</p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-start flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-danger hover:bg-light cursor-pointer">
                                    <i class="ph ph-sign-out"></i> Çıkış Yap
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="flex items-center">
                    <a href="{{ route('app.dashboard') }}" class="logo logo-normal">
                        <img src="{{ asset('template/v1/assets/img/logo.svg') }}" alt="Logo">
                    </a>
                    <a href="{{ route('app.dashboard') }}" class="logo-small">
                        <img src="{{ asset('template/v1/assets/img/logo-small.svg') }}" alt="Logo">
                    </a>
                    <a href="{{ route('app.dashboard') }}" class="dark-logo">
                        <img src="{{ asset('template/v1/assets/img/logo-white.svg') }}" alt="Logo">
                    </a>
                </div>
                <button class="sidenav-toggle-btn btn border-0 p-0 active" id="toggle_btn" aria-label="toggle">
                    <i class="icon icon-panel-right-open align-middle"></i>
                </button>
            </div>
            <div class="sidebar-inner" data-simplebar="">
                <div id="sidebar-menu" class="sidebar-menu">
                    <ul role="menu" aria-label="Ana menü">
                        <li class="menu-title" aria-disabled="true"><span>{{ auth()->user()?->tenant?->name }}</span></li>
                        <li>
                            <a href="{{ route('app.dashboard') }}" class="{{ request()->routeIs('app.dashboard') ? 'active' : '' }}">
                                <i class="ph-duotone ph-squares-four"></i><span>Kontrol Paneli</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <div class="page-wrapper">
            <main>
                <div class="p-3 lg:py-6 lg:px-0">
                    @if (auth('central_web')->check())
                        <div class="bg-warning-transparent text-warning border border-warning rounded-md px-4 py-3 text-sm mb-4 flex items-center justify-between gap-3">
                            <span><i class="ph ph-eye me-1"></i> Süper Admin olarak <strong>{{ auth()->user()?->name }}</strong> hesabını görüntülüyorsunuz.</span>
                            <form method="POST" action="{{ route('impersonation.leave') }}">
                                @csrf
                                <button type="submit" class="btn-sm bg-white border border-warning text-warning hover:bg-warning hover:text-white cursor-pointer">
                                    Yönetici Paneline Dön
                                </button>
                            </form>
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
            <footer class="footer px-6 pb-3 flex items-center justify-center gap-2">
                <p>{{ date('Y') }} &copy; {{ auth()->user()?->tenant?->name }} — ERP</p>
            </footer>
        </div>
    </div>

    <script src="{{ asset('template/v1/assets/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('template/v1/assets/libs/preline/preline.js') }}"></script>
    <script type="module" src="{{ asset('template/v1/assets/js/script.js') }}"></script>
</body>
</html>
