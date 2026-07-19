<header class="navbar-header flex items-center max-lg:w-full">
    <div class="topbar-menu flex items-center justify-between w-full gap-2">
        <div class="flex items-center gap-3">
            <a id="mobile_btn" class="mobile-btn lg:hidden" href="#sidebar" aria-label="menu">
                <i class="icon icon-menu"></i>
            </a>
            <a href="{{ route('central.web.tenants.index') }}" class="logo">
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
                    <p class="px-2 py-1.5 text-sm font-semibold text-title mb-0">{{ auth('central_web')->user()?->name }}</p>
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
