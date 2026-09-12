<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>سنتر دريم — الإدارة التعليمية</title>
    <meta name="theme-color" content="#132238">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="is-page-loading">
    <div class="page-loader" data-page-loader aria-hidden="true">
        <span class="page-loader-mark"><i></i><i></i><i></i></span>
        <span>جاري تحميل الصفحة…</span>
    </div>
    <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
    <div class="app-shell">
        @include('partials.sidebar')
        <div class="app-workspace">
            @include('partials.global-header')
            <main class="main-content" id="main-content">
                @if (session('status'))
                    <div class="flash-message" data-flash-message role="status" aria-live="polite">
                        <span class="flash-message-icon" aria-hidden="true">✓</span>
                        <span class="flash-message-copy">{{ session('status') }}</span>
                        <button class="flash-message-close" type="button" data-dismiss-flash aria-label="إغلاق الرسالة">×</button>
                    </div>
                @endif
                @yield('content')
            </main>
            @include('partials.footer')
        </div>
    </div>
</body>
</html>
