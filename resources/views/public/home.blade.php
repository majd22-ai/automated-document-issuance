 @extends('layouts.public')

@section('title', 'الرئيسية - منصة الإصدار الآلي للوثائق الرسمية')

@section('content')
  <section class="gov-page-title" aria-labelledby="portal-title">
    <div class="gov-container"><h2 id="portal-title">بوابة الخدمات الإلكترونية</h2></div>
  </section>

  <section class="gov-welcome-section" id="about-platform">
    <div class="gov-container gov-welcome-grid">
      <div class="gov-welcome-copy">
        <h2>مرحبًا بكم في منصة الإصدار الآلي للوثائق الرسمية في الجمهورية اليمنية</h2>
        <h3>يسرّنا تقديم خدماتنا الإلكترونية من خلال منصة رقمية موحدة:</h3>
        <ul>
          <li>الاطلاع على متطلبات وإجراءات إصدار وتجديد بطاقة الهوية الشخصية.</li>
          <li>التعرف على خدمات إصدار وتجديد جوازات السفر.</li>
          <li>استعراض خدمات إصدار وتجديد رخص القيادة.</li>
          <li>تقديم الطلبات ومتابعة حالتها إلكترونيًا بعد تسجيل الدخول.</li>
        </ul>
        <div class="gov-welcome-actions">
  <a href="{{ route('login') }}" class="btn btn-primary">
    تسجيل الدخول
  </a>

  <a href="{{ route('register') }}" class="btn btn-outline">
    التسجيل لأول مرة
  </a>
</div>
      </div>
      <div class="gov-welcome-picture" role="img" aria-label="مشهد من معالم اليمن">
        <div class="gov-picture-caption">اليمن | نحو خدمات رقمية أسهل</div>
      </div>
    </div>
  </section>

  <div class="gov-container"><div class="gold-divider"><span class="diamond"></span></div></div>

  <section class="gov-services-section section" id="services">
    <div class="gov-container">
      <div class="section-header">
        <h2>خدمات المنصة</h2>
        <p>اختر القسم المطلوب للاطلاع على الخدمات والمتطلبات</p>
      </div>
      <div class="gov-services-grid">
        <a href="{{ url('/services/identity') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true">▣</div>
          <h3>بطاقة الهوية الشخصية</h3>
          <p>إصدار لأول مرة، تجديد البطاقة، وإصدار بدل فاقد.</p>
          <span class="gov-service-more">3 خدمات <span aria-hidden="true">←</span></span>
        </a>
        <a href="{{ url('/services/passports') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true">▤</div>
          <h3>جواز السفر</h3>
          <p>إصدار جواز سفر لأول مرة وتجديد جواز السفر.</p>
          <span class="gov-service-more">خدمتان <span aria-hidden="true">←</span></span>
        </a>
        <a href="{{ url('/services/driving-licenses') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true">◉</div>
          <h3>رخصة القيادة</h3>
          <p>إصدار رخصة قيادة لأول مرة وتجديد رخصة القيادة.</p>
          <span class="gov-service-more">خدمتان <span aria-hidden="true">←</span></span>
        </a>
      </div>
    </div>
  </section>

  <section class="gov-notice-section" id="contact">
    <div class="gov-container">
      <p><strong>تنويه:</strong> هذه المنصة نموذج لمشروع تخرج أكاديمي، ولا تمثل جهة حكومية مخوّلة قانونيًا بإصدار الوثائق الرسمية.</p>
    </div>
  </section>
@endsection
