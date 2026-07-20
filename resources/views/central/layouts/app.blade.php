<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', __('Admin Panel')) | ERP Merkez</title>
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
        @include('central.layouts.partials.header')
        @include('central.layouts.partials.sidebar')

        <div class="page-wrapper">
            <main>
                <div class="p-3 lg:py-6 lg:px-0">
                    @if (session('status'))
                        <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
                            <ul class="mb-0 list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            <footer class="footer px-6 pb-3 flex items-center justify-center gap-2">
                <p>{{ date('Y') }} &copy; {{ __('ERP Central Admin Panel') }}</p>
            </footer>
        </div>
    </div>

    <script src="{{ asset('template/v1/assets/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('template/v1/assets/libs/preline/preline.js') }}"></script>
    <script type="module" src="{{ asset('template/v1/assets/js/script.js') }}"></script>
</body>
</html>
