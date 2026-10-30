 @extends('layouts.public')

@section('title', 'الرئيسية - منصة الإصدار الآلي للوثائق الرسمية')

@section('content')
  <div class="gov-notice-section">
    <div class="gov-container">
      <a href="{{ asset('demo/index.html') }}">تجربة الواجهات: الطلبات والمواعيد والدفع ومراجعة الموظف <x-icon name="arrow-left" /></a>
      <span> — بيانات تجريبية دون إرسال فعلي.</span>
    </div>
  </div>
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
      <div class="gov-welcome-picture" role="img" aria-label="مجمع الإصدار الآلي من الداخل" style="background-image:linear-gradient(0deg,#0e273099,transparent 60%),url('{{ asset('assets/images/banner/الاصدار الالي من الداخل 2.jfif') }}');">
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
          <div class="gov-service-icon" aria-hidden="true"><x-icon name="identity" /></div>
          <h3>بطاقة الهوية الشخصية</h3>
          <p>إصدار لأول مرة، تجديد البطاقة، وإصدار بدل فاقد.</p>
          <span class="gov-service-more">3 خدمات <span aria-hidden="true"><x-icon name="arrow-left" /></span></span>
        </a>
        <a href="{{ url('/services/passports') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true"><x-icon name="passport" /></div>
          <h3>جواز السفر</h3>
          <p>إصدار جواز سفر لأول مرة وتجديد جواز السفر.</p>
          <span class="gov-service-more">خدمتان <span aria-hidden="true"><x-icon name="arrow-left" /></span></span>
        </a>
        <a href="{{ url('/services/driving-licenses') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true"><x-icon name="car" /></div>
          <h3>رخصة القيادة</h3>
          <p>إصدار رخصة قيادة لأول مرة وتجديد رخصة القيادة.</p>
          <span class="gov-service-more">خدمتان <span aria-hidden="true"><x-icon name="arrow-left" /></span></span>
        </a>
        <a href="{{ route('appointments.book') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true"><x-icon name="calendar" /></div>
          <h3>خدمات المواعيد</h3>
          <p>حجز موعد لمراجعة المركز واستكمال إجراءات الخدمة أو استلام الوثيقة.</p>
          <span class="gov-service-more">حجز موعد <x-icon name="arrow-left" /></span>
        </a>
        <a href="{{ route('inquiry.reference') }}" class="gov-service-card">
          <div class="gov-service-icon" aria-hidden="true"><x-icon name="file" /></div>
          <h3>خدمات الاستعلام والمتابعة</h3>
          <p>الاستعلام عن حالة الطلب ومتابعة مراحل إنجاز المعاملة باستخدام الرقم المرجعي.</p>
          <span class="gov-service-more">الاستعلام عن طلب <x-icon name="arrow-left" /></span>
        </a>
      </div>
    </div>
  </section>

  <section class="section" id="service-requirements" aria-labelledby="service-requirements-heading">
    <div class="gov-container">
      <div class="section-header">
        <h2 id="service-requirements-heading">متطلبات الخدمات</h2>
        <p>اختر نوع الوثيقة للاطلاع على المستندات والشروط الخاصة بكل خدمة.</p>
      </div>
      <div class="gov-services-grid">
        <a class="gov-service-card" href="{{ route('services.civil-registry.guide', 'identity') }}">
          <h3>متطلبات البطاقة الشخصية</h3>
          <p>متطلبات الإصدار لأول مرة والتجديد وبدل الفاقد.</p>
          <span class="gov-service-more">عرض المتطلبات <x-icon name="arrow-left" /></span>
        </a>
        <a class="gov-service-card" href="{{ route('services.passports').'#requirements' }}">
          <h3>متطلبات جواز السفر</h3>
          <p>المستندات المطلوبة لإصدار جواز السفر وتجديده.</p>
          <span class="gov-service-more">عرض المتطلبات <x-icon name="arrow-left" /></span>
        </a>
        <a class="gov-service-card" href="{{ route('services.driving-licenses').'#requirements' }}">
          <h3>متطلبات رخصة القيادة</h3>
          <p>المستندات والفحص الطبي ومتطلبات الإصدار والتجديد.</p>
          <span class="gov-service-more">عرض المتطلبات <x-icon name="arrow-left" /></span>
        </a>
      </div>
    </div>
  </section>

  <section class="section" id="faq" aria-labelledby="faq-heading">
    <div class="gov-container">
      <div class="section-header">
        <h2 id="faq-heading">الأسئلة الشائعة</h2>
        <p>إجابات مختصرة حول استخدام النموذج التجريبي.</p>
      </div>
      <details class="card">
        <summary>كيف أقدم طلبًا جديدًا؟</summary>
        <p>افتح رابط تجربة الواجهات، واختر «طلب جديد»، ثم حدد الخدمة وأدخل بيانات تجريبية وأرفق المستندات وأكد إرسال الطلب.</p>
      </details>
      <details class="card">
        <summary>كيف أتابع طلبي أو أستكمل النواقص؟</summary>
        <p>بعد تسجيل الدخول افتح طلباتك من قائمة حسابك أو من الاستعلام في أعلى الصفحة، ثم افتح تفاصيل الطلب لعرض حالته وسجل المراجعة. إذا طُلب استكمال يمكنك تعديل الحقول أو المرفقات المسموح بها وإعادة الإرسال.</p>
      </details>
      <details class="card">
        <summary>هل الدفع والوثائق في المنصة حقيقية؟</summary>
        <p>لا، المنصة نموذج أكاديمي. الدفع والوثائق محاكاة فقط؛ استخدم بيانات وملفات تجريبية، ولا تدخل بيانات مالية حقيقية.</p>
      </details>
      <details class="card">
        <summary>أين تُحفظ الطلبات والمرفقات التجريبية؟</summary>
        <p>تُحفظ طلبات الحساب في قاعدة بيانات المشروع، وتُرفع مرفقاتها إلى مساحة خاصة لا يطلع عليها إلا المستخدمون المخوّلون. أما تجربة الواجهات المنفصلة فتستخدم تخزين المتصفح المحلي.</p>
      </details>
    </div>
  </section>

  <section class="gov-notice-section" id="contact">
    <div class="gov-container">
      <p><strong>تنويه:</strong> هذه المنصة نموذج لمشروع تخرج أكاديمي، ولا تمثل جهة حكومية مخوّلة قانونيًا بإصدار الوثائق الرسمية.</p>
    </div>
  </section>
  @include('components.location-map')
@endsection
