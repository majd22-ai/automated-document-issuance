 <!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'منصة الإصدار الآلي للوثائق الرسمية')</title>

    {{-- الخطوط الأساسية --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    {{-- ملفات CSS و JavaScript الأساسية --}}
    @vite([
        'resources/css/design-system.css',
        'resources/css/public.css',
        'resources/css/citizen.css',
        'resources/js/app.js',
        'resources/js/homepage-fix.js'
    ])

    {{-- تنسيقات إضافية خاصة بالصفحات --}}
    @stack('styles')

</head>

<body>

    {{-- الترويسة والقائمة الرئيسية --}}
    @include('components.public-header')

    {{-- محتوى الصفحة --}}
    <main id="main-content">
        @yield('content')
    </main>

    {{-- التذييل --}}
    @include('components.public-footer')

    {{-- سكربتات إضافية خاصة بالصفحات --}}
    @stack('scripts')

</body>

</html>