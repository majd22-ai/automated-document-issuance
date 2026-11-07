@php
  $operationsWorkspace = auth()->check() && in_array(auth()->user()->role, ['admin','employee'], true) && request()->routeIs('admin.*','employee.*','profile.*','integrations.civil-registry-demo*','identity-workflow.*');
@endphp
 <!DOCTYPE html>
<html lang="ar" dir="rtl" translate="no" class="notranslate">
<head>
  <meta charset="UTF-8">
  <meta name="google" content="notranslate">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', 'منصة الإصدار الآلي للوثائق الرسمية')</title>

  @if(request()->routeIs('home'))
    <x-image-preload src="assets/images/banner/platform-interior.webp" sizes="(max-width:1200px) 46vw, 560px" media="(min-width:901px)" />
    <x-image-preload src="assets/images/banner/header-left.webp" sizes="260px" media="(max-width:640px)" />
  @else
    @php
      $preloadDocument = match(request()->route()?->getName()) {
        'services.identity' => 'assets/images/services/identity-document.webp',
        'services.passports' => 'assets/images/services/yemeni-passport.webp',
        'services.driving-licenses' => 'assets/images/services/driving-license.webp',
        default => null,
      };
    @endphp
    @if($preloadDocument)<x-image-preload :src="$preloadDocument" sizes="(max-width:640px) 100vw, 65vw" />@endif
  @endif

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
  @if($operationsWorkspace || request()->routeIs('citizen.dashboard'))
  <link rel="stylesheet" href="{{ asset('css/operations-workspace.css') }}?v={{ filemtime(public_path('css/operations-workspace.css')) }}">
  @endif
  <link rel="stylesheet" href="{{ asset('css/service-banner.css') }}?v={{ filemtime(public_path('css/service-banner.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/icons.css') }}?v={{ filemtime(public_path('css/icons.css')) }}">
</head>

<body class="{{ request()->routeIs('home') ? 'gov-home-page' : 'gov-inner-page' }}">
  @php
    $bannerRoute = request()->routeIs('register.*') ? 'register' : request()->route()?->getName();
    $serviceBanner = match ($bannerRoute) {
      'services.identity' => [
        'title' => 'خدمات البطاقة الشخصية', 'subtitle' => 'إصدار الهوية الشخصية وتجديدها واستبدالها',
        'agency' => 'assets/images/banner/civil-registry-logo.webp', 'agencyAlt' => 'شعار مصلحة الأحوال المدنية والسجل المدني',
        'agencyWidth' => 1080, 'agencyHeight' => 1092,
        'document' => 'assets/images/services/identity-document.webp', 'documentAlt' => 'صورة البطاقة الشخصية اليمنية',
        'documentWidth' => 1200, 'documentHeight' => 670,
      ],
      'services.passports' => [
        'title' => 'خدمات جواز السفر', 'subtitle' => 'خدمات إصدار جواز السفر اليمني وتجديده',
        'agency' => 'assets/images/services/passports-logo.webp', 'agencyAlt' => 'شعار مصلحة الهجرة والجوازات والجنسية',
        'agencyWidth' => 600, 'agencyHeight' => 600,
        'document' => 'assets/images/services/yemeni-passport.webp', 'documentAlt' => 'صورة جواز سفر الجمهورية اليمنية',
        'documentWidth' => 1200, 'documentHeight' => 896,
      ],
      'services.driving-licenses' => [
        'title' => 'خدمات رخصة القيادة', 'subtitle' => 'خدمات إصدار رخصة القيادة اليمنية وتجديدها',
        'agency' => 'assets/images/services/traffic-logo.webp', 'agencyAlt' => 'شعار الإدارة العامة للمرور',
        'agencyWidth' => 423, 'agencyHeight' => 405,
        'document' => 'assets/images/services/driving-license.webp', 'documentAlt' => 'صورة رخصة قيادة يمنية',
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
        'agency' => 'assets/images/banner/moi-logo.webp', 'agencyAlt' => 'شعار وزارة الداخلية',
        'agencyWidth' => 1188, 'agencyHeight' => 1600,
        'document' => 'assets/images/banner/unified-issuance-building.webp', 'documentAlt' => 'مبنى مجمع الإصدار الآلي الموحد',
        'documentWidth' => 1600, 'documentHeight' => 1200,
      ],
      default => null,
    };
  @endphp
  @if($operationsWorkspace)
    <div class="workspace-brand" dir="rtl"><strong>منصة الإصدار الآلي للوثائق الرسمية</strong><span>{{ auth()->user()->role === 'admin' ? 'مساحة الإدارة والرقابة' : 'مساحة الموظف المختص' }}</span></div>
  @elseif($serviceBanner)
    @include('components.service-banner')
  @else
    @include('components.page-banner')
  @endif
  @include('components.public-header')

  <main id="main-content"
        class="{{ request()->routeIs('login', 'register', 'register.*', 'password.*') ? 'auth-page-wrapper' : '' }}">
    @if($operationsWorkspace)
    <div class="ops-shell">
      @include('components.operations-sidebar')
      <div class="ops-content">
        <div class="ops-location"><a href="{{ route(auth()->user()->role.'.dashboard') }}">{{ auth()->user()->role === 'admin' ? 'مساحة الإدارة' : 'مساحة الموظف' }}</a><span aria-hidden="true">/</span><span>@yield('title')</span></div>
        @yield('content')
      </div>
    </div>
    @else
      @yield('content')
    @endif
  </main>

  @include('components.public-footer')

  @if(auth()->check() && \App\Services\IdentityAttestations::canUseSimulation(auth()->user()))
    <link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}?v={{ filemtime(public_path('css/identity-attestation.css')) }}">
    @include('components.mobile-attestation-simulator')
  @endif

  @stack('scripts')
  @if($operationsWorkspace)<script src="{{ asset('js/operations-workspace.js') }}?v={{ filemtime(public_path('js/operations-workspace.js')) }}" defer></script>@endif
  @auth<script src="{{ asset('js/notification-count.js') }}?v={{ filemtime(public_path('js/notification-count.js')) }}" data-count-url="{{ route('notifications.unread-count') }}" defer></script>@endauth

  {{-- Birth-certificate path: restrict services popup --}}
  @auth
  @if(auth()->user()->isBirthCertificatePath())
  <div id="bc-restrict-modal" role="dialog" aria-modal="true" aria-labelledby="bc-restrict-title" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;max-width:440px;width:90%;padding:2rem;text-align:center;position:relative;direction:rtl;">
      <div style="font-size:2.5rem;margin-bottom:.75rem;">&#x1F512;</div>
      <h2 id="bc-restrict-title" style="font-size:1.2rem;font-weight:700;margin-bottom:.75rem;color:#1a1a2e;">&#x627;&#x644;&#x62E;&#x62F;&#x645;&#x629; &#x63A;&#x64A;&#x631; &#x645;&#x62A;&#x627;&#x62D;&#x629;</h2>
      <p style="color:#444;line-height:1.7;margin-bottom:1.5rem;">&#x64A;&#x62C;&#x628; &#x639;&#x644;&#x64A;&#x643; <strong>&#x625;&#x635;&#x62F;&#x627;&#x631; &#x628;&#x637;&#x627;&#x642;&#x629; &#x634;&#x62E;&#x635;&#x64A;&#x629;</strong> &#x623;&#x648;&#x644;&#x627;&#x64B; &#x644;&#x62A;&#x643;&#x648;&#x646; &#x628;&#x642;&#x64A;&#x629; &#x627;&#x644;&#x62E;&#x62F;&#x645;&#x627;&#x62A; &#x645;&#x62A;&#x627;&#x62D;&#x629; &#x644;&#x643;.<br>&#x64A;&#x645;&#x643;&#x646;&#x643; &#x62D;&#x627;&#x644;&#x64A;&#x64B;&#x627; &#x62A;&#x642;&#x62F;&#x64A;&#x645; &#x637;&#x644;&#x628; &#x625;&#x635;&#x62F;&#x627;&#x631; &#x628;&#x637;&#x627;&#x642;&#x629; &#x634;&#x62E;&#x635;&#x64A;&#x629; &#x623;&#x648; &#x625;&#x635;&#x62F;&#x627;&#x631; &#x634;&#x647;&#x627;&#x62F;&#x629; &#x645;&#x64A;&#x644;&#x627;&#x62F; &#x641;&#x642;&#x637;.</p>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('services.catalog', 'identity-first-issue') }}" style="background:#1a6cad;color:#fff;padding:.6rem 1.4rem;border-radius:8px;text-decoration:none;font-weight:600;">&#x625;&#x635;&#x62F;&#x627;&#x631; &#x628;&#x637;&#x627;&#x642;&#x629; &#x634;&#x62E;&#x635;&#x64A;&#x629;</a>
        <button onclick="document.getElementById('bc-restrict-modal').style.display='none'" style="background:#eee;color:#333;padding:.6rem 1.4rem;border-radius:8px;border:none;cursor:pointer;font-weight:600;">&#x625;&#x63A;&#x644;&#x627;&#x642;</button>
      </div>
    </div>
  </div>
  <script>
  (function () {
    var ALLOWED_CODES = ['identity-first-issue', 'birth-certificate-first-issue'];
    var modal = document.getElementById('bc-restrict-modal');
    function isRestricted(href) {
      if (!href || href === '#') return false;
      var m = href.match(/\/services\/catalog\/([^?#\/]+)/);
      if (m) return ALLOWED_CODES.indexOf(m[1]) === -1;
      if (/\/services\/(passports|driving-licenses)(\/|$)/.test(href)) return true;
      if (/\/services\/identity\/(renewal|lost-replacement)/.test(href)) return true;
      return false;
    }
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (!a) return;
      if (isRestricted(a.getAttribute('href'))) { e.preventDefault(); modal.style.display = 'flex'; }
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
  })();
  </script>
  @endif
  @endauth
</body>
</html>
