<section class="service-banner service-banner-background" dir="rtl" aria-labelledby="gov-banner-title">
  <div class="gov-container service-banner-grid">
    <div class="service-banner-identity">
      <div class="service-banner-logos" aria-label="شعارات المنصة والجهة المختصة">
        <x-responsive-image class="service-banner-emblem" src="assets/images/banner/yemen-eagle.webp" sizes="(max-width:900px) 76px, 94px" alt="شعار الجمهورية اليمنية" />
        <x-responsive-image class="service-banner-platform" src="assets/images/logos/unified-issuance-logo.webp" sizes="(max-width:900px) 90px, 104px" alt="شعار مركز الإصدار الآلي الموحد" />
        <x-responsive-image class="service-banner-agency {{ $serviceBanner['agencyClass'] ?? '' }}" :src="$serviceBanner['agency']" sizes="(max-width:900px) 76px, 94px" :alt="$serviceBanner['agencyAlt']" />
      </div>
      <div class="service-banner-copy">
        <p class="service-banner-eyebrow">منصة الإصدار الآلي للوثائق الرسمية</p>
        <h1 id="gov-banner-title">{{ $serviceBanner['title'] }}</h1>
        <p class="service-banner-subtitle">{{ $serviceBanner['subtitle'] }}</p>
      </div>
    </div>
    <figure class="service-banner-document">
      <x-responsive-image :src="$serviceBanner['document']" sizes="(max-width:640px) 100vw, 65vw" :alt="$serviceBanner['documentAlt']" priority="high" />
    </figure>
  </div>
</section>
