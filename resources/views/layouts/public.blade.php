
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', 'منصة الإصدار الآلي للوثائق الرسمية')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

  @vite([
    'resources/css/design-system.css',
    'resources/css/public.css',
    'resources/css/citizen.css',
    'resources/js/app.js'
  ])

  @stack('styles')
</head>
<body>

  @include('components.public-header')

  <main>
    @yield('content')
  </main>

  @include('components.public-footer')

  @stack('scripts')

</body>
</html>

