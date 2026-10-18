<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#F6F4F0" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#121110" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Tempa">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Tema dipasang sebelum render agar tidak berkedip. --}}
    <script>
        (function () {
            var pref = 'system';
            try { pref = localStorage.getItem('tempa:theme') || 'system'; } catch (e) {}
            var dark = pref === 'dark' || (pref === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) {
                m.setAttribute('content', dark ? '#121110' : '#F6F4F0');
            });
        })();
    </script>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
