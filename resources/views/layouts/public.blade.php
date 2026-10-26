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
  <link rel="stylesheet" href="{{ asset('css/service-banner.css') }}?v={{ filemtime(public_path('css/service-banner.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/icons.css') }}?v={{ filemtime(public_path('css/icons.css')) }}">
</head>

<body class="{{ request()->routeIs('home') ? 'gov-home-page' : 'gov-inner-page' }}">
  @php
    $bannerRoute = request()->routeIs('register.*') ? 'register' : request()->route()?->getName();
    $serviceBanner = match ($bannerRoute) {
      'services.identity' => [
        'title' => 'خدمات البطاقة الشخصية', 'subtitle' => 'إصدار الهوية الشخصية وتجديدها واستبدالها',
        'agency' => 'assets/images/banner/الاحوال المدنية.png', 'agencyAlt' => 'شعار مصلحة الأحوال المدنية والسجل المدني',
        'agencyWidth' => 1080, 'agencyHeight' => 1092,
        'document' => 'assets/images/services/identity-document.jpg', 'documentAlt' => 'صورة البطاقة الشخصية اليمنية',
        'documentWidth' => 1200, 'documentHeight' => 670,
      ],
      'services.passports' => [
        'title' => 'خدمات جواز السفر', 'subtitle' => 'خدمات إصدار جواز السفر اليمني وتجديده',
        'agency' => 'assets/images/services/passports-logo.png', 'agencyAlt' => 'شعار مصلحة الهجرة والجوازات والجنسية',
        'agencyWidth' => 600, 'agencyHeight' => 600,
        'document' => 'assets/images/services/yemeni-passport.jpg', 'documentAlt' => 'صورة جواز سفر الجمهورية اليمنية',
        'documentWidth' => 1200, 'documentHeight' => 896,
      ],
      'services.driving-licenses' => [
        'title' => 'خدمات رخصة القيادة', 'subtitle' => 'خدمات إصدار رخصة القيادة اليمنية وتجديدها',
        'agency' => 'assets/images/services/traffic-logo.png', 'agencyAlt' => 'شعار الإدارة العامة للمرور',
        'agencyWidth' => 423, 'agencyHeight' => 405,
        'document' => 'assets/images/services/driving-license.jpg', 'documentAlt' => 'صورة رخصة قيادة يمنية',
        'documentWidth' => 1200, 'documentHeight' => 670,
      ],
      'appointments.book', 'inquiry.reference', 'inquiry.mine', 'login', 'register' => [
        'title' => match ($bannerRoute) {
          'appointments.book' => 'خدمات المواعيد',
          'inquiry.mine' => 'الاستعلام عن جميع طلباتي',
          'login' => 'تسجيل الدخول',
          'register' => 'التسجيل لأول مرة',
          default => 'الاستعلام عن حالة الطلب',
        },
        'subtitle' => match ($bannerRoute) {
          'appointments.book' => 'حجز المواعيد لمراجعة المركز واستكمال إجراءات الخدمة',
          'login' => 'سجّل الدخول إلى حسابك للوصول إلى خدمات المنصة ومتابعة طلباتك',
          'register' => 'أنشئ حسابك للاستفادة من خدمات المنصة وتقديم طلباتك إلكترونيًا',
          default => 'الاستعلام عن الطلبات ومتابعة مراحل إنجاز المعاملات',
        },
        'agency' => 'assets/images/banner/moi-logo.png', 'agencyAlt' => 'شعار وزارة الداخلية',
        'agencyWidth' => 1188, 'agencyHeight' => 1600,
        'document' => 'assets/images/banner/مجمع الاصدار الالي الموحد.jpg', 'documentAlt' => 'مبنى مجمع الإصدار الآلي الموحد',
        'documentWidth' => 2048, 'documentHeight' => 1536,
      ],
      default => null,
    };
  @endphp
  @if($serviceBanner)
    @include('components.service-banner')
  @else
    @include('components.page-banner')
  @endif
  @include('components.public-header')

  <main id="main-content"
        class="{{ request()->routeIs('login', 'register', 'register.*', 'password.*') ? 'auth-page-wrapper' : '' }}">
    @yield('content')
  </main>

  @include('components.public-footer')

  @stack('scripts')
</body>
</html>
