<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="flex items-center">
            <a href="{{ route('central.web.tenants.index') }}" class="logo logo-normal">
                <img src="{{ asset('template/v1/assets/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('central.web.tenants.index') }}" class="logo-small">
                <img src="{{ asset('template/v1/assets/img/logo-small.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('central.web.tenants.index') }}" class="dark-logo">
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
                <li class="menu-title" aria-disabled="true"><span>Platform</span></li>
                <li>
                    <a href="{{ route('central.web.tenants.index') }}" class="{{ request()->routeIs('central.web.tenants.*') ? 'active' : '' }}">
                        <i class="ph-duotone ph-buildings"></i><span>Tenant'lar</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
