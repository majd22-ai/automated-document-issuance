@php
  $isHome = $bannerHome ?? request()->routeIs('home');
  $pageHeading = $bannerTitle ?? trim(explode(' - ', strip_tags($__env->yieldContent('title', 'منصة الإصدار الآلي للوثائق الرسمية')))[0]);
@endphp
<section class="gov-header-visual gov-page-banner {{ $isHome ? 'gov-banner-home' : 'gov-banner-inner' }}" aria-labelledby="gov-banner-title">
  <div class="gov-header-landscape gov-header-landscape-right" aria-hidden="true">
    <img src="{{ asset('assets/images/banner/header-right.png') }}" alt="">
  </div>
  <div class="gov-header-landscape gov-header-landscape-left" aria-hidden="true">
    <img src="{{ asset('assets/images/banner/header-left.png') }}" alt="">
  </div>
  <div class="gov-header-identity">
    <div class="gov-header-logos">
      <img class="gov-national-emblem" src="{{ asset('assets/images/banner/yemen-eagle.png') }}" alt="شعار الجمهورية اليمنية">
      <img class="gov-platform-logo" src="{{ asset('assets/images/logos/unified-issuance-logo.png') }}" alt="شعار مركز الإصدار الآلي الموحد">
      <img class="gov-ministry-logo" src="{{ asset('assets/images/banner/moi-logo.png') }}" alt="شعار وزارة الداخلية">
    </div>
    <h1 id="gov-banner-title" data-banner-title>{{ $isHome ? 'منصة الإصدار الآلي للوثائق الرسمية' : $pageHeading }}</h1>
    @if($isHome)
      <p class="gov-header-english" dir="ltr">AUTOMATED OFFICIAL DOCUMENT ISSUANCE &amp; MANAGEMENT PLATFORM</p>
      <div class="gov-header-tags">
        <span>سهولة الوصول</span><span>تنظيم الطلبات</span><span>حماية البيانات</span><span>خدمات رقمية</span>
      </div>
    @endif
  </div>
</section>
