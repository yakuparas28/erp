<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', __('Dashboard')) | {{ auth()->user()?->tenant?->name ?? 'ERP' }}</title>
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
                <div class="flex items-center gap-2 ms-auto">
<div class="header-item hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                <button type="button" class="hs-dropdown-toggle topbar-link items-center justify-center" aria-label="{{ __('Language') }}">
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
                                    <i class="ph ph-sign-out"></i> {{ __('Log Out') }}
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
                                <i class="ph-duotone ph-squares-four"></i><span>{{ __('Dashboard') }}</span>
                            </a>
                        </li>
                        @if (auth()->user()?->can('view stock') || auth()->user()?->can('manage products') || auth()->user()?->can('manage warehouses') || auth()->user()?->can('perform stock counts') || auth()->user()?->can('manage warehouse transfers') || auth()->user()?->can('manage partners') || auth()->user()?->can('manage routes') || auth()->user()?->can('manage reordering rules'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Inventory') }}</span></li>
                            @can('manage products')
                                <li>
                                    <a href="{{ route('app.inventory.products.index') }}" class="{{ request()->routeIs('app.inventory.products.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-package"></i><span>{{ __('Products') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage warehouses')
                                <li>
                                    <a href="{{ route('app.inventory.warehouses.index') }}" class="{{ request()->routeIs('app.inventory.warehouses.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-warehouse"></i><span>{{ __('Warehouses') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('view stock')
                                <li>
                                    <a href="{{ route('app.inventory.stock.index') }}" class="{{ request()->routeIs('app.inventory.stock.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-stack"></i><span>{{ __('Stock') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('perform stock counts')
                                <li>
                                    <a href="{{ route('app.inventory.adjustments.index') }}" class="{{ request()->routeIs('app.inventory.adjustments.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-clipboard-text"></i><span>{{ __('Stock Counts') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage warehouse transfers')
                                <li>
                                    <a href="{{ route('app.inventory.transfers.index') }}" class="{{ request()->routeIs('app.inventory.transfers.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-arrows-left-right"></i><span>{{ __('Transfers') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage partners')
                                <li>
                                    <a href="{{ route('app.inventory.partners.index') }}" class="{{ request()->routeIs('app.inventory.partners.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-handshake"></i><span>{{ __('Partners') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage routes')
                                <li>
                                    <a href="{{ route('app.inventory.putaway.index') }}" class="{{ request()->routeIs('app.inventory.putaway.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-map-pin-line"></i><span>{{ __('Putaway Rules') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage reordering rules')
                                <li>
                                    <a href="{{ route('app.inventory.reordering.index') }}" class="{{ request()->routeIs('app.inventory.reordering.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-arrows-clockwise"></i><span>{{ __('Reordering') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage routes')
                                <li>
                                    <a href="{{ route('app.inventory.routes.index') }}" class="{{ request()->routeIs('app.inventory.routes.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-flow-arrow"></i><span>{{ __('Routes') }}</span>
                                    </a>
                                </li>
                            @endcan
                        @endif
                        @if (auth()->user()?->can('create purchase orders') || auth()->user()?->can('confirm purchase orders'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Purchasing') }}</span></li>
                            <li>
                                <a href="{{ route('app.purchase.orders.index') }}" class="{{ request()->routeIs('app.purchase.orders.*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-shopping-cart"></i><span>{{ __('Purchase Orders') }}</span>
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()?->can('create sales orders') || auth()->user()?->can('confirm sales orders'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Sales') }}</span></li>
                            <li>
                                <a href="{{ route('app.sales.orders.index') }}" class="{{ request()->routeIs('app.sales.orders.*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Orders') }}</span>
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()?->can('manage users') || auth()->user()?->can('manage roles'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Administration') }}</span></li>
                            @can('manage users')
                                <li>
                                    <a href="{{ route('app.users.index') }}" class="{{ request()->routeIs('app.users.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-users"></i><span>{{ __('Users') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage roles')
                                <li>
                                    <a href="{{ route('app.roles.index') }}" class="{{ request()->routeIs('app.roles.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-shield-check"></i><span>{{ __('Roles & Permissions') }}</span>
                                    </a>
                                </li>
                            @endcan
                        @endif
                        @if (auth()->user()?->hasRole('Tenant Admin'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Settings') }}</span></li>
                            <li>
                                <a href="{{ route('app.settings.mail') }}" class="{{ request()->routeIs('app.settings.mail*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-envelope-simple"></i><span>{{ __('Email Settings') }}</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('app.settings.templates') }}" class="{{ request()->routeIs('app.settings.templates*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-file-text"></i><span>{{ __('Notification Templates') }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </aside>

        <div class="page-wrapper">
            <main>
                <div class="p-3 lg:py-6 lg:px-0">
                    @if (auth('central_web')->check())
                        <div class="bg-warning-transparent text-warning border border-warning rounded-md px-4 py-3 text-sm mb-4 flex items-center justify-between gap-3">
                            <span><i class="ph ph-eye me-1"></i> {{ __("You are viewing :name's account as Super Admin.", ['name' => auth()->user()?->name]) }}</span>
                            <form method="POST" action="{{ route('impersonation.leave') }}">
                                @csrf
                                <button type="submit" class="btn-sm bg-white border border-warning text-warning hover:bg-warning hover:text-white cursor-pointer">
                                    {{ __('Back to Admin Panel') }}
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
