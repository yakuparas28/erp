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
    <style>
        /* Kill every list bullet under the sidebar. The nested .submenu ul was
           still rendering ::marker even after list-style: none because <li> has
           display: list-item by default — switching those inner li elements to
           display: block prevents the marker from being generated at all. */
        #sidebar-menu ul, #sidebar-menu .submenu ul {
            list-style: none !important;
            list-style-type: none !important;
            padding-left: 0 !important;
            margin-left: 0 !important;
        }
        #sidebar-menu li,
        #sidebar-menu .submenu ul li {
            list-style: none !important;
            list-style-type: none !important;
            list-style-image: none !important;
            display: block !important;
        }
        #sidebar-menu li::marker { content: '' !important; display: none !important; }
        #sidebar-menu li::before { content: none !important; }
    </style>
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
                        @can('manage partners')
                            <li>
                                <a href="{{ route('app.inventory.partners.index') }}" class="{{ request()->routeIs('app.inventory.partners.*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-users-three"></i><span>{{ __('Contacts') }}</span>
                                </a>
                            </li>
                        @endcan
                        @if (auth()->user()?->can('view stock') || auth()->user()?->can('manage products') || auth()->user()?->can('manage warehouses') || auth()->user()?->can('perform stock counts') || auth()->user()?->can('manage warehouse transfers') || auth()->user()?->can('manage partners') || auth()->user()?->can('manage routes') || auth()->user()?->can('manage reordering rules'))
                            @php $inventoryActive = request()->routeIs('app.inventory.*'); @endphp
                            <li class="submenu {{ $inventoryActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $inventoryActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-package"></i><span>{{ __('Inventory') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>

                            @if (auth()->user()?->can('perform stock counts') || auth()->user()?->can('manage warehouse transfers') || auth()->user()?->can('manage reordering rules') || auth()->user()?->can('perform scrap operations') || auth()->user()?->can('approve landed costs'))
                                <li class="menu-title" aria-disabled="true"><span class="text-xs opacity-70">— {{ __('Operations') }}</span></li>
                                @can('manage warehouse transfers')
                                    <li>
                                        <a href="{{ route('app.inventory.transfers.index') }}" class="{{ request()->routeIs('app.inventory.transfers.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-arrows-left-right"></i><span>{{ __('Transfers') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.inventory.transfer-batches.index') }}" class="{{ request()->routeIs('app.inventory.transfer-batches.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-stack-plus"></i><span>{{ __('Batch Transfers') }}</span>
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
                                @can('perform scrap operations')
                                    <li>
                                        <a href="{{ route('app.inventory.scraps.index') }}" class="{{ request()->routeIs('app.inventory.scraps.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-trash"></i><span>{{ __('Scrap') }}</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('perform stock counts')
                                    <li>
                                        <a href="{{ route('app.inventory.barcode.index') }}" class="{{ request()->routeIs('app.inventory.barcode.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-barcode"></i><span>{{ __('Barcode Operator') }}</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('manage reordering rules')
                                    <li>
                                        <a href="{{ route('app.inventory.reordering.index') }}" class="{{ request()->routeIs('app.inventory.reordering.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-arrows-clockwise"></i><span>{{ __('Replenishment') }}</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('approve landed costs')
                                    <li>
                                        <a href="{{ route('app.inventory.landed-costs.index') }}" class="{{ request()->routeIs('app.inventory.landed-costs.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-cardholder"></i><span>{{ __('Landed Costs') }}</span>
                                        </a>
                                    </li>
                                @endcan
                            @endif

                            @can('manage products')
                                <li class="menu-title" aria-disabled="true"><span class="text-xs opacity-70">— {{ __('Products') }}</span></li>
                                <li>
                                    <a href="{{ route('app.inventory.products.index') }}" class="{{ request()->routeIs('app.inventory.products.*') || request()->routeIs('app.inventory.templates.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-package"></i><span>{{ __('Products') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.categories.index') }}" class="{{ request()->routeIs('app.inventory.categories.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-folders"></i><span>{{ __('Product Categories') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.brands.index') }}" class="{{ request()->routeIs('app.inventory.brands.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-copyright"></i><span>{{ __('Brands') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.attributes.index') }}" class="{{ request()->routeIs('app.inventory.attributes.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-swatches"></i><span>{{ __('Attributes') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.lots.index') }}" class="{{ request()->routeIs('app.inventory.lots.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-barcode"></i><span>{{ __('Lots / Serial Numbers') }}</span>
                                    </a>
                                </li>
                            @endcan

                            @can('view stock')
                                <li class="menu-title" aria-disabled="true"><span class="text-xs opacity-70">— {{ __('Reporting') }}</span></li>
                                <li>
                                    <a href="{{ route('app.inventory.stock.index') }}" class="{{ request()->routeIs('app.inventory.stock.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-stack"></i><span>{{ __('Stock') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.moves') }}" class="{{ request()->routeIs('app.inventory.reports.moves') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-clock-counter-clockwise"></i><span>{{ __('Moves History') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.valuation') }}" class="{{ request()->routeIs('app.inventory.reports.valuation') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-calculator"></i><span>{{ __('Inventory Valuation') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.locations') }}" class="{{ request()->routeIs('app.inventory.reports.locations') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-map-pin"></i><span>{{ __('Locations Report') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.forecasted') }}" class="{{ request()->routeIs('app.inventory.reports.forecasted') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-chart-line-up"></i><span>{{ __('Forecasted Report') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.warehouse-analysis') }}" class="{{ request()->routeIs('app.inventory.reports.warehouse-analysis') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-chart-bar"></i><span>{{ __('Warehouse Analysis') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.inventory.reports.consignment') }}" class="{{ request()->routeIs('app.inventory.reports.consignment') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-handshake"></i><span>{{ __('Consignment Report') }}</span>
                                    </a>
                                </li>
                            @endcan

                            @if (auth()->user()?->can('manage warehouses') || auth()->user()?->can('manage partners') || auth()->user()?->can('manage routes'))
                                <li class="menu-title" aria-disabled="true"><span class="text-xs opacity-70">— {{ __('Configuration') }}</span></li>
                                @can('manage warehouses')
                                    <li>
                                        <a href="{{ route('app.inventory.warehouses.index') }}" class="{{ request()->routeIs('app.inventory.warehouses.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-warehouse"></i><span>{{ __('Warehouses') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.inventory.operation-types.index') }}" class="{{ request()->routeIs('app.inventory.operation-types.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-list-checks"></i><span>{{ __('Operation Types') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.inventory.storage-categories.index') }}" class="{{ request()->routeIs('app.inventory.storage-categories.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-stack-simple"></i><span>{{ __('Storage Categories') }}</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('manage products')
                                    <li>
                                        <a href="{{ route('app.inventory.uoms.index') }}" class="{{ request()->routeIs('app.inventory.uoms.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-ruler"></i><span>{{ __('Units of Measure') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.inventory.package-types.index') }}" class="{{ request()->routeIs('app.inventory.package-types.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-package"></i><span>{{ __('Package Types') }}</span>
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
                                @can('manage routes')
                                    <li>
                                        <a href="{{ route('app.inventory.putaway.index') }}" class="{{ request()->routeIs('app.inventory.putaway.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-map-pin-line"></i><span>{{ __('Putaway Rules') }}</span>
                                        </a>
                                    </li>
                                @endcan
                            @endif
                                </ul>
                            </li>
                        @endif
                        @module('purchase')
                        @if (auth()->user()?->can('create purchase orders') || auth()->user()?->can('confirm purchase orders'))
                            @php $purchaseActive = request()->routeIs('app.purchase.*'); @endphp
                            <li class="submenu {{ $purchaseActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $purchaseActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-shopping-cart"></i><span>{{ __('Purchasing') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li>
                                        <a href="{{ route('app.purchase.orders.index') }}" class="{{ request()->routeIs('app.purchase.orders.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-shopping-cart"></i><span>{{ __('Purchase Orders') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.purchase.goods-receipts.index') }}" class="{{ request()->routeIs('app.purchase.goods-receipts.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-package"></i><span>{{ __('Goods Receipts') }}</span>
                                        </a>
                                    </li>
                                    @can('post journal entries')
                                        @module('accounting')
                                            <li>
                                                <a href="{{ route('app.accounting.purchase-invoices.index') }}" class="{{ request()->routeIs('app.accounting.purchase-invoices.*') ? 'active' : '' }}">
                                                    <i class="ph-duotone ph-file-text"></i><span>{{ __('Purchase Invoices') }}</span>
                                                </a>
                                            </li>
                                        @endmodule
                                    @endcan
                                </ul>
                            </li>
                        @endif
                        @endmodule
                        @module('sales')
                        @if (auth()->user()?->can('create sales orders') || auth()->user()?->can('confirm sales orders'))
                            @php $salesActive = request()->routeIs('app.sales.*'); @endphp
                            <li class="submenu {{ $salesActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $salesActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    <li>
                                        <a href="{{ route('app.sales.quotations.index') }}" class="{{ request()->routeIs('app.sales.quotations.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-file-text"></i><span>{{ __('Quotations') }}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('app.sales.orders.index') }}" class="{{ request()->routeIs('app.sales.orders.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Orders') }}</span>
                                        </a>
                                    </li>
                                    @can('create sales orders')
                                        <li>
                                            <a href="{{ route('app.sales.delivery-notes.index') }}" class="{{ request()->routeIs('app.sales.delivery-notes.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-note-pencil"></i><span>{{ __('Delivery Notes') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.sales.carriers.index') }}" class="{{ request()->routeIs('app.sales.carriers.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-truck"></i><span>{{ __('Delivery Carriers') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('post journal entries')
                                        @module('accounting')
                                            <li>
                                                <a href="{{ route('app.accounting.sales-invoices.index') }}" class="{{ request()->routeIs('app.accounting.sales-invoices.*') ? 'active' : '' }}">
                                                    <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Invoices') }}</span>
                                                </a>
                                            </li>
                                        @endmodule
                                    @endcan
                                </ul>
                            </li>
                        @endif
                        @endmodule
                        @module('accounting')
                        @if (auth()->user()?->can('manage chart of accounts') || auth()->user()?->can('post journal entries') || auth()->user()?->can('register payments'))
                            @php $accountingActive = request()->routeIs('app.accounting.*'); @endphp
                            <li class="submenu {{ $accountingActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $accountingActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-book-open-text"></i><span>{{ __('Accounting') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.accounts.index') }}" class="{{ request()->routeIs('app.accounting.accounts.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-book-open-text"></i><span>{{ __('Chart of Accounts') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.currencies.index') }}" class="{{ request()->routeIs('app.accounting.currencies.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-coin"></i><span>{{ __('Currencies') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.exchange-rates.index') }}" class="{{ request()->routeIs('app.accounting.exchange-rates.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-currency-circle-dollar"></i><span>{{ __('Exchange Rates') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.journal-entries.index') }}" class="{{ request()->routeIs('app.accounting.journal-entries.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-notebook"></i><span>{{ __('Journal Entries') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('post journal entries')
                                <li>
                                    <a href="{{ route('app.accounting.purchase-invoices.index') }}" class="{{ request()->routeIs('app.accounting.purchase-invoices.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-file-text"></i><span>{{ __('Purchase Invoices') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('post journal entries')
                                <li>
                                    <a href="{{ route('app.accounting.sales-invoices.index') }}" class="{{ request()->routeIs('app.accounting.sales-invoices.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Invoices') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @can('register payments')
                                <li>
                                    <a href="{{ route('app.accounting.cash-flow.index') }}" class="{{ request()->routeIs('app.accounting.cash-flow.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-chart-line-up"></i><span>{{ __('Cash Flow Dashboard') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.receipts.index') }}" class="{{ request()->routeIs('app.accounting.receipts.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-hand-coins"></i><span>{{ __('Customer Receipts') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.disbursements.index') }}" class="{{ request()->routeIs('app.accounting.disbursements.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-money"></i><span>{{ __('Supplier Payments') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.incoming-checks.index') }}" class="{{ request()->routeIs('app.accounting.incoming-checks.*') || request()->routeIs('app.accounting.checks-and-notes.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-note"></i><span>{{ __('Incoming Checks & Notes') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.outgoing-checks.index') }}" class="{{ request()->routeIs('app.accounting.outgoing-checks.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-note-pencil"></i><span>{{ __('Outgoing Checks & Notes') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.card-payments.index') }}" class="{{ request()->routeIs('app.accounting.card-payments.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-credit-card"></i><span>{{ __('Card Payments') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.bank-statements.index') }}" class="{{ request()->routeIs('app.accounting.bank-statements.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-file-arrow-up"></i><span>{{ __('Bank Statements') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.payments.index') }}" class="{{ request()->routeIs('app.accounting.payments.index') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-wallet"></i><span>{{ __('All Payments') }}</span>
                                    </a>
                                </li>
                            @endcan
                            @if (auth()->user()?->hasRole('Tenant Admin'))
                                <li>
                                    <a href="{{ route('app.accounting.cash-bank-accounts.index') }}" class="{{ request()->routeIs('app.accounting.cash-bank-accounts.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-vault"></i><span>{{ __('Cash & Bank Accounts') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.pos-terminals.index') }}" class="{{ request()->routeIs('app.accounting.pos-terminals.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-terminal"></i><span>{{ __('POS Terminals') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('app.accounting.settings.edit') }}" class="{{ request()->routeIs('app.accounting.settings.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-gear"></i><span>{{ __('Accounting Settings') }}</span>
                                    </a>
                                </li>
                            @endif
                                </ul>
                            </li>
                        @endif
                        @endmodule
                        @module('hr')
                            @php $hrActive = request()->routeIs('app.hr.*'); @endphp
                            <li class="submenu {{ $hrActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $hrActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-users"></i><span>{{ __('Human Resources') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    @can('manage employees')
                                        <li>
                                            <a href="{{ route('app.hr.employees.index') }}" class="{{ request()->routeIs('app.hr.employees.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-users-three"></i><span>{{ __('Employees') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @if (auth()->user()?->hasRole('Tenant Admin'))
                                        <li>
                                            <a href="{{ route('app.hr.payroll.index') }}" class="{{ request()->routeIs('app.hr.payroll.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-money"></i><span>{{ __('Payroll') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.hr.salary-advances.index') }}" class="{{ request()->routeIs('app.hr.salary-advances.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-hand-coins"></i><span>{{ __('Salary Advances') }}</span>
                                            </a>
                                        </li>
                                    @endif
                                    @can('manage departments')
                                        <li>
                                            <a href="{{ route('app.hr.departments.index') }}" class="{{ request()->routeIs('app.hr.departments.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-buildings"></i><span>{{ __('Departments') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('submit own leave')
                                        <li>
                                            <a href="{{ route('app.hr.leaves.mine') }}" class="{{ request()->routeIs('app.hr.leaves.mine') || request()->routeIs('app.hr.leaves.store') || request()->routeIs('app.hr.leaves.cancel') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-calendar-check"></i><span>{{ __('My Leave Requests') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('approve leave first level')
                                        <li>
                                            <a href="{{ route('app.hr.leave-approvals.first') }}" class="{{ request()->routeIs('app.hr.leave-approvals.first') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-user-check"></i><span>{{ __('Unit Manager Approvals') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('approve leave second level')
                                        <li>
                                            <a href="{{ route('app.hr.leave-approvals.second') }}" class="{{ request()->routeIs('app.hr.leave-approvals.second') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-check-square"></i><span>{{ __('General Manager Approvals') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('view leave monitoring')
                                        <li>
                                            <a href="{{ route('app.hr.leave-monitoring.index') }}" class="{{ request()->routeIs('app.hr.leave-monitoring.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-eye"></i><span>{{ __('Leave Monitoring') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage leave balances')
                                        <li>
                                            <a href="{{ route('app.hr.leave-balances.index') }}" class="{{ request()->routeIs('app.hr.leave-balances.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-scales"></i><span>{{ __('Leave Balances') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage leave configuration')
                                        <li>
                                            <a href="{{ route('app.hr.leave-types.index') }}" class="{{ request()->routeIs('app.hr.leave-types.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-tag"></i><span>{{ __('Leave Types') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.hr.leave-config.index') }}" class="{{ request()->routeIs('app.hr.leave-config.index') || request()->routeIs('app.hr.leave-config.holidays.*') || request()->routeIs('app.hr.leave-config.critical-dates.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-gear-six"></i><span>{{ __('Leave Configuration') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.hr.leave-config.hourly.index') }}" class="{{ request()->routeIs('app.hr.leave-config.hourly.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-clock"></i><span>{{ __('Hourly Leave Configuration') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.hr.consumption-rules.index') }}" class="{{ request()->routeIs('app.hr.consumption-rules.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-scroll"></i><span>{{ __('Consumption & Accrual Rules') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </li>
                        @endmodule
                        @module('fleet')
                            @php $fleetActive = request()->routeIs('app.fleet.*'); @endphp
                            <li class="submenu {{ $fleetActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $fleetActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-car"></i><span>{{ __('Fleet') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    @can('view fleet dashboard')
                                        <li>
                                            <a href="{{ route('app.fleet.dashboard') }}" class="{{ request()->routeIs('app.fleet.dashboard') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-gauge"></i><span>{{ __('Fleet Dashboard') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('view fleet calendar')
                                        <li>
                                            <a href="{{ route('app.fleet.calendar.index') }}" class="{{ request()->routeIs('app.fleet.calendar.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-calendar"></i><span>{{ __('Fleet Calendar') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('reserve vehicle')
                                        <li>
                                            <a href="{{ route('app.fleet.reservations.create') }}" class="{{ request()->routeIs('app.fleet.reservations.create') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-plus-circle"></i><span>{{ __('New Reservation') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('view own reservations')
                                        <li>
                                            <a href="{{ route('app.fleet.reservations.index') }}" class="{{ request()->routeIs('app.fleet.reservations.index') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-ticket"></i><span>{{ __('My Reservations') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('approve-vehicle-request')
                                        <li>
                                            <a href="{{ route('app.fleet.approvals.index') }}" class="{{ request()->routeIs('app.fleet.approvals.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-check-square"></i><span>{{ __('Pending Approvals') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('confirm vehicle delivery')
                                        <li>
                                            <a href="{{ route('app.fleet.deliveries.index') }}" class="{{ request()->routeIs('app.fleet.deliveries.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-key"></i><span>{{ __('Key Handover') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage vehicles')
                                        <li>
                                            <a href="{{ route('app.fleet.vehicles.index') }}" class="{{ request()->routeIs('app.fleet.vehicles.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-clipboard-text"></i><span>{{ __('Vehicles') }}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('app.fleet.projects.index') }}" class="{{ request()->routeIs('app.fleet.projects.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-folders"></i><span>{{ __('Projects') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage vehicle calendar')
                                        <li>
                                            <a href="{{ route('app.fleet.vehicle-calendar.index') }}" class="{{ request()->routeIs('app.fleet.vehicle-calendar.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-calendar-plus"></i><span>{{ __('Vehicle Calendar') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage maintenance records')
                                        <li>
                                            <a href="{{ route('app.fleet.maintenance.index') }}" class="{{ request()->routeIs('app.fleet.maintenance.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-wrench"></i><span>{{ __('Maintenance Records') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage usage rules')
                                        <li>
                                            <a href="{{ route('app.fleet.usage-rules.show') }}" class="{{ request()->routeIs('app.fleet.usage-rules.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-book-open"></i><span>{{ __('Usage Rules') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('view usage report')
                                        <li>
                                            <a href="{{ route('app.fleet.reports.usage') }}" class="{{ request()->routeIs('app.fleet.reports.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-chart-bar"></i><span>{{ __('Usage Report') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage fleet task settings')
                                        <li>
                                            <a href="{{ route('app.fleet.settings.tasks') }}" class="{{ request()->routeIs('app.fleet.settings.tasks*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-timer"></i><span>{{ __('Scheduled Tasks') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </li>
                        @endmodule
                        @module('expenses')
                            @php $expensesActive = request()->routeIs('app.expenses.*'); @endphp
                            <li class="submenu {{ $expensesActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $expensesActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-wallet"></i><span>{{ __('Expenses') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
                                    @can('submit own expense')
                                        <li>
                                            <a href="{{ route('app.expenses.mine') }}" class="{{ request()->routeIs('app.expenses.mine') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-receipt"></i><span>{{ __('My Expenses') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('approve expense')
                                        <li>
                                            <a href="{{ route('app.expenses.approvals.index') }}" class="{{ request()->routeIs('app.expenses.approvals.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-check-square"></i><span>{{ __('Expense Approvals') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                    @can('manage expense categories')
                                        <li>
                                            <a href="{{ route('app.expenses.categories.index') }}" class="{{ request()->routeIs('app.expenses.categories.*') ? 'active' : '' }}">
                                                <i class="ph-duotone ph-tag"></i><span>{{ __('Expense Categories') }}</span>
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </li>
                        @endmodule
                        @if (auth()->user()?->can('manage users') || auth()->user()?->can('manage roles'))
                            @php $adminActive = request()->routeIs('app.users.*') || request()->routeIs('app.roles.*'); @endphp
                            <li class="submenu {{ $adminActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $adminActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-user-gear"></i><span>{{ __('Administration') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
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
                                </ul>
                            </li>
                        @endif
                        @can('manage settings')
                            @php $settingsActive = request()->routeIs('app.settings.*') || request()->routeIs('app.approval-workflows.*'); @endphp
                            <li class="submenu {{ $settingsActive ? 'active' : '' }}">
                                <a href="javascript:void(0);" class="{{ $settingsActive ? 'active subdrop' : '' }}">
                                    <i class="ph-duotone ph-gear"></i><span>{{ __('Settings') }}</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <ul>
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
                                    <li>
                                        <a href="{{ route('app.approval-workflows.index') }}" class="{{ request()->routeIs('app.approval-workflows.*') ? 'active' : '' }}">
                                            <i class="ph-duotone ph-flow-arrow"></i><span>{{ __('Approval Workflows') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endcan
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
