<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#2f6fe4" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#22272e" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="EnglishPath">
<meta name="description" content="Belajar bahasa Inggris dari nol sampai mahir dengan jalur belajar terkunci berbasis CEFR.">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<title>{{ isset($title) ? $title.' · ' : '' }}EnglishPath</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
<script>
    (function () {
        try {
            var t = localStorage.getItem('ep-theme');
            if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
        } catch (e) {}
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
