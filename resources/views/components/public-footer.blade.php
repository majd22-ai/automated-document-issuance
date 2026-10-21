<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <h4>عن المنصة</h4>
        <p>
          منصة رقمية موحدة تهدف إلى تسهيل الوصول إلى خدمات إصدار وتجديد الوثائق الرسمية
          في الجمهورية اليمنية، بما يشمل البطاقة الشخصية ورخصة القيادة وجواز السفر.
        </p>
        <p class="text-muted" style="margin-top:12px; color:#999; font-size:12px;">
          <x-icon name="warning" /> نموذج أكاديمي — لا يمثل جهة حكومية مخوّلة قانونيًا بإصدار وثائق رسمية.
        </p>
      </div>
      <div class="footer-col">
        <h4>الخدمات المدعومة</h4>
        <a href="{{ url('/services/identity') }}">البطاقة الشخصية</a>
        <a href="{{ url('/services/passports') }}">جواز السفر</a>
        <a href="{{ url('/services/driving-licenses') }}">رخصة القيادة</a>
      </div>
      <div class="footer-col">
        <h4>روابط سريعة</h4>
        <a href="{{ route('home') }}">الصفحة الرئيسية</a>
        <a href="{{ route('home').'#service-requirements' }}">متطلبات الخدمات</a>
        <a href="{{ asset('demo/index.html').'#requests' }}">تتبع الطلب</a>
        <a href="{{ route('home').'#faq' }}">الأسئلة الشائعة</a>
      </div>
      <div class="footer-col">
        <h4>التواصل والدعم</h4>
        <a href="#"><x-icon name="phone" /> الدعم الفني</a>
        <a href="#"><x-icon name="mail" /> البريد الإلكتروني</a>
        <a href="#"><x-icon name="location" /> العنوان الرسمي</a>
      </div>
    </div>
    <div class="footer-bottom">
      © {{ date('Y') }} منصة الإصدار الآلي للوثائق الرسمية — جميع الحقوق محفوظة | مشروع تخرج أكاديمي
    </div>
  </div>
</footer>
