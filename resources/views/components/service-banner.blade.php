<section class="service-banner service-banner-background" dir="rtl" aria-labelledby="gov-banner-title">
  <div class="gov-container service-banner-grid">
    <div class="service-banner-identity">
      <div class="service-banner-logos" aria-label="شعارات المنصة والجهة المختصة">
        <img class="service-banner-emblem" src="{{ asset('assets/images/banner/yemen-eagle.png') }}" alt="شعار الجمهورية اليمنية" width="2560" height="1492">
        <img class="service-banner-platform" src="{{ asset('assets/images/logos/unified-issuance-logo.png') }}" alt="شعار مركز الإصدار الآلي الموحد">
        <img class="service-banner-agency {{ $serviceBanner['agencyClass'] ?? '' }}" src="{{ asset($serviceBanner['agency']) }}" alt="{{ $serviceBanner['agencyAlt'] }}" width="{{ $serviceBanner['agencyWidth'] }}" height="{{ $serviceBanner['agencyHeight'] }}">
      </div>
      <div class="service-banner-copy">
        <p class="service-banner-eyebrow">منصة الإصدار الآلي للوثائق الرسمية</p>
        <h1 id="gov-banner-title">{{ $serviceBanner['title'] }}</h1>
        <p class="service-banner-subtitle">{{ $serviceBanner['subtitle'] }}</p>
      </div>
    </div>
    <figure class="service-banner-document">
      <img src="{{ asset($serviceBanner['document']) }}" alt="{{ $serviceBanner['documentAlt'] }}" width="{{ $serviceBanner['documentWidth'] }}" height="{{ $serviceBanner['documentHeight'] }}" fetchpriority="high">
    </figure>
  </div>
</section>
