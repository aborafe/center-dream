<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>سنتر دريم — الإدارة التعليمية</title>
    <meta name="theme-color" content="#132238">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
    <div class="app-shell">
        @include('partials.sidebar')
        <div class="app-workspace">
            @include('partials.global-header')
            <main class="main-content" id="main-content">
                @if (session('status'))
                    <div class="flash-message" role="status" aria-live="polite">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
            @include('partials.footer')
        </div>
    </div>
</body>
</html>
