 {{-- الترويسة العامة للمنصة --}}
<header class="gov-site-header">
  <div class="gov-header-visual">
    <div class="gov-header-landscape gov-header-landscape-right" aria-hidden="true">
      <img src="{{ asset('assets/images/banner/header-right.png') }}" alt="">
    </div>
    <div class="gov-header-landscape gov-header-landscape-left" aria-hidden="true">
      <img src="{{ asset('assets/images/banner/header-left.png') }}" alt="">
    </div>
    <div class="gov-header-identity gov-container">
      <div class="gov-header-logos">
        <img src="{{ asset('assets/images/banner/cra-logo.png') }}" alt="شعار مصلحة الأحوال المدنية والسجل المدني">
        <img src="{{ asset('assets/images/banner/yemen-eagle.png') }}" alt="الطير الجمهوري">
        <img src="{{ asset('assets/images/banner/moi-logo.png') }}" alt="شعار وزارة الداخلية">
      </div>
      <h1>منصة الإصدار الآلي للوثائق الرسمية</h1>
      <p class="gov-header-english" dir="ltr">Automated Official Document Issuance &amp; Management Platform</p>
      <p class="gov-header-subtitle">الجمهورية اليمنية | بوابة الخدمات الإلكترونية</p>
      <div class="gov-header-tags" aria-label="مزايا المنصة">
        <span>سهولة الوصول</span><span>تنظيم الطلبات</span><span>حماية البيانات</span><span>خدمات رقمية</span>
      </div>
    </div>
  </div>

  <div class="gov-contact-strip">
    <div class="gov-container gov-contact-inner">
      <div class="gov-contact-text">منصة تجريبية أكاديمية وليست جهة حكومية لإصدار الوثائق</div>
      <div class="gov-contact-social" aria-label="روابط مساعدة">
        <a href="{{ url('/#about-platform') }}">عن المنصة</a>
        <span aria-hidden="true">✦</span>
        <a href="{{ url('/#contact') }}">التواصل</a>
      </div>
    </div>
  </div>

  <nav class="gov-main-nav" aria-label="القائمة الرئيسية">
    <div class="gov-container gov-nav-inner">
      <input class="gov-nav-check" type="checkbox" id="gov-nav-check" aria-label="عرض القائمة على الهاتف">
      <label for="gov-nav-check" class="gov-nav-toggle">☰ <span>القائمة</span></label>
      <div class="gov-nav-links">
        <a class="gov-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">⌂ الرئيسية</a>
        <div class="gov-nav-dropdown">
          <button type="button" class="gov-nav-link gov-dropdown-trigger {{ request()->is('services*') || request()->is('appointments*') ? 'is-active' : '' }}" aria-expanded="false" aria-controls="gov-services-menu">▣ بوابة الخدمات <span class="gov-caret" aria-hidden="true">⌄</span></button>
          <div class="gov-dropdown-content" id="gov-services-menu">
            <a href="{{ route('services.identity') }}">🪪 خدمات بطاقة الهوية</a>
            <a href="{{ route('services.passports') }}">📘 خدمات جواز السفر</a>
            <a href="{{ route('services.driving-licenses') }}">🚗 خدمات رخصة القيادة</a>
            <a href="{{ route('appointments.book') }}">📅 خدمة حجز المواعيد</a>
          </div>
        </div>
        <a class="gov-nav-link" href="{{ url('/#services') }}">☷ متطلبات الخدمات</a>
        <a class="gov-nav-link" href="{{ url('/login') }}">⌕ تتبع الطلب</a>
        <a class="gov-nav-link" href="{{ url('/#about-platform') }}">ⓘ عن المنصة</a>
        <a class="gov-nav-link" href="{{ url('/#contact') }}">✉ تواصل معنا</a>
      </div>
      <div class="gov-nav-actions">
        @auth
          @if(auth()->user()->role === 'admin')
            <a href="{{ url('/admin/dashboard') }}" class="gov-nav-button">لوحة التحكم</a>
          @elseif(auth()->user()->role === 'employee')
            <a href="{{ url('/employee/dashboard') }}" class="gov-nav-button">لوحة التحكم</a>
          @else
            <a href="{{ url('/citizen/dashboard') }}" class="gov-nav-button">لوحة التحكم</a>
          @endif
        @else
          <a href="{{ url('/login') }}" class="gov-nav-button">دخول المستخدم</a>
        @endauth
      </div>
    </div>
  </nav>
</header>
