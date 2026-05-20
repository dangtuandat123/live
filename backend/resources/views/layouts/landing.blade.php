<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Nền tảng livestream bán hàng, chốt đơn tự động. Tăng doanh thu 300% với công nghệ AI hỗ trợ chốt đơn realtime.">
        <meta name="keywords" content="livestream, bán hàng, chốt đơn, ecommerce, live selling">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ config('app.name', 'LiveStream') }} - Nền tảng Livestream Chốt Đơn #1">
        <meta property="og:description" content="Nền tảng livestream bán hàng, chốt đơn tự động. Tăng doanh thu 300%.">

        <title>{{ config('app.name', 'LiveStream') }} - Nền tảng Livestream Chốt Đơn #1</title>

        <!-- Theme Script -->
        <script>
            try {
                const theme = localStorage.getItem('theme') || 'system';
                if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (_) {}
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Styles & Scripts -->
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-background text-foreground min-h-screen selection:bg-primary/20 selection:text-primary-foreground">
        @yield('content')
    </body>
</html>
