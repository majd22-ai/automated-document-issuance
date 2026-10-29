<footer class="site-footer" dir="rtl" aria-label="معلومات المنصة والسياسات والمساعدة">
  <div class="container">
    <div class="footer-grid">
      <section class="footer-col" aria-labelledby="footer-about-heading">
        <h2 id="footer-about-heading">عن المنصة</h2>
        <nav aria-label="عن المنصة"><ul class="footer-links">
          <li><a href="{{ route('platform.page', 'about').'#overview' }}">نبذة عن المنصة</a></li>
          <li><a href="{{ route('platform.page', 'about').'#goals' }}">أهداف المنصة</a></li>
          <li><a href="{{ route('platform.page', 'about').'#workflow' }}">مراحل معالجة الطلب</a></li>
        </ul></nav>
      </section>
      <section class="footer-col" aria-labelledby="footer-policies-heading">
        <h2 id="footer-policies-heading">السياسات والإرشادات</h2>
        <nav aria-label="السياسات والإرشادات"><ul class="footer-links">
          <li><a href="{{ route('platform.page', 'privacy') }}">سياسة الخصوصية</a></li>
          <li><a href="{{ route('platform.page', 'terms') }}">شروط الاستخدام</a></li>
          <li><a href="{{ route('platform.page', 'data-protection') }}">حماية البيانات</a></li>
        </ul></nav>
      </section>
      <section class="footer-col footer-support" aria-labelledby="footer-help-heading">
        <h2 id="footer-help-heading">المساعدة والدعم</h2>
        <nav aria-label="المساعدة والدعم"><ul class="footer-links">
          <li><a href="{{ route('home').'#faq' }}"><x-icon name="search" /> الأسئلة الشائعة</a></li>
          <li><a href="{{ route('platform.page', 'guide') }}"><x-icon name="file" /> دليل استخدام المنصة</a></li>
          <li><a href="{{ route('help.technical-issue') }}"><x-icon name="warning" /> الإبلاغ عن مشكلة تقنية</a></li>
        </ul></nav>
      </section>
    </div>
    <div class="footer-bottom">
      <p>© {{ date('Y') }} منصة الإصدار الآلي للوثائق الرسمية — جميع الحقوق محفوظة</p>
      <p>مشروع تخرج أكاديمي — نسخة تجريبية</p>
    </div>
  </div>
</footer>
