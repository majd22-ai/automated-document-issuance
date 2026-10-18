 <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', 'منصة الإصدار الآلي للوثائق الرسمية')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

  @vite([
    'resources/css/design-system.css',
    'resources/css/citizen.css',
    'resources/js/app.js'
  ])

  <link rel="stylesheet"
        href="{{ asset('css/portal-theme.css') }}?v={{ filemtime(public_path('css/portal-theme.css')) }}">

  <script src="{{ asset('js/portal-theme.js') }}?v={{ filemtime(public_path('js/portal-theme.js')) }}"
          defer></script>

  @stack('styles')
</head>

<body class="{{ request()->routeIs('home') ? 'gov-home-page' : 'gov-inner-page' }}">
  @include('components.page-banner')
  @include('components.public-header')

  <main id="main-content"
        class="{{ request()->routeIs('login', 'register', 'password.*') ? 'auth-page-wrapper' : '' }}">
    @yield('content')
  </main>

  @include('components.public-footer')

  @stack('scripts')
</body>
</html>
