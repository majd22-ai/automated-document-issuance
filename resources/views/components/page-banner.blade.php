@php
  $isHome = $bannerHome ?? request()->routeIs('home');
  $pageHeading = $bannerTitle ?? trim(explode(' - ', strip_tags($__env->yieldContent('title', 'منصة الإصدار الآلي للوثائق الرسمية')))[0]);
@endphp
<section class="gov-header-visual gov-page-banner {{ $isHome ? 'gov-banner-home' : 'gov-banner-inner' }}" aria-labelledby="gov-banner-title">
  <div class="gov-header-landscape gov-header-landscape-right" aria-hidden="true">
    <x-responsive-image src="assets/images/banner/header-right.webp" sizes="(max-width:640px) 260px, (max-width:900px) 45vw, 36vw" alt="" />
  </div>
  <div class="gov-header-landscape gov-header-landscape-left" aria-hidden="true">
    <x-responsive-image src="assets/images/banner/header-left.webp" sizes="(max-width:640px) 260px, (max-width:900px) 45vw, 36vw" priority="high" alt="" />
  </div>
  <div class="gov-header-identity">
    <div class="gov-header-logos">
      <x-responsive-image class="gov-national-emblem" src="assets/images/banner/yemen-eagle.webp" sizes="(max-width:640px) 58px, 96px" alt="شعار الجمهورية اليمنية" />
      <x-responsive-image class="gov-platform-logo" src="assets/images/logos/unified-issuance-logo.webp" sizes="(max-width:640px) 84px, 120px" alt="شعار مركز الإصدار الآلي الموحد" />
      <x-responsive-image class="gov-ministry-logo" src="assets/images/banner/moi-logo.webp" sizes="(max-width:640px) 58px, 96px" alt="شعار وزارة الداخلية" />
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
