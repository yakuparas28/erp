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
    @yield('content')

    <script src="{{ asset('template/v1/assets/libs/preline/preline.js') }}"></script>
</body>
</html>
