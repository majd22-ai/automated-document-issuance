@extends('layouts.public')
@section('title', 'خدمة حجز المواعيد - منصة الإصدار الآلي')

@section('content')

{{-- ===== عنوان الصفحة ===== --}}
<div class="gov-page-title">
  <div class="gov-container">
    <h2>📅 خدمة حجز المواعيد</h2>
  </div>
</div>

{{-- ===== مقدمة الصفحة ===== --}}
<section class="appt-hero">
  <div class="gov-container appt-hero-inner">
    <div class="appt-hero-text">
      <h3>احجز موعدك الآن بكل سهولة</h3>
      <p>
        تتيح لك هذه الخدمة حجز موعد في أي مركز خدمة حكومي معتمد لإتمام إجراءات الحصول على وثائقك الرسمية
        — بطاقة الهوية، جواز السفر، أو رخصة القيادة — دون الحاجة إلى الانتظار الطويل.
      </p>
      <ul class="appt-benefits">
        <li>✅ توفير الوقت والجهد</li>
        <li>✅ اختيار الموعد المناسب لك</li>
        <li>✅ استلام تأكيد فوري برسالة</li>
        <li>✅ إمكانية التعديل أو الإلغاء قبل 24 ساعة</li>
      </ul>
    </div>
    <div class="appt-hero-icon" aria-hidden="true">📋</div>
  </div>
</section>

{{-- ===== خطوات الحجز ===== --}}
<section class="appt-steps-section">
  <div class="gov-container">
    <h3 class="appt-section-title">كيف تحجز موعدك؟</h3>
    <div class="appt-steps-grid">
      <div class="appt-step">
        <div class="appt-step-num">١</div>
        <h4>سجّل الدخول</h4>
        <p>ادخل إلى حسابك على المنصة أو أنشئ حساباً جديداً مجاناً</p>
      </div>
      <div class="appt-step">
        <div class="appt-step-num">٢</div>
        <h4>اختر الخدمة</h4>
        <p>حدد نوع الخدمة التي تحتاجها: بطاقة هوية، جواز سفر، أو رخصة قيادة</p>
      </div>
      <div class="appt-step">
        <div class="appt-step-num">٣</div>
        <h4>اختر الموعد</h4>
        <p>حدد التاريخ والوقت المناسبَين لك من المواعيد المتاحة</p>
      </div>
      <div class="appt-step">
        <div class="appt-step-num">٤</div>
        <h4>تأكيد الحجز</h4>
        <p>استلم رسالة تأكيد بتفاصيل موعدك وتعليمات الحضور</p>
      </div>
    </div>
  </div>
</section>

{{-- ===== الخدمات المتاحة للحجز ===== --}}
<section class="appt-services-section">
  <div class="gov-container">
    <h3 class="appt-section-title">الخدمات المتاحة للحجز</h3>
    <div class="appt-services-grid">

      <div class="appt-service-card">
        <div class="appt-service-icon">🪪</div>
        <h4>بطاقة الهوية الشخصية</h4>
        <p>إصدار لأول مرة، تجديد، أو بدل فاقد لبطاقة الهوية الوطنية</p>
        <div class="appt-service-meta">
          <span>⏱ مدة الخدمة: 30 دقيقة</span>
          <span>📍 مراكز السجل المدني</span>
        </div>
        @auth
          <a href="{{ route('citizen.appointments.index') }}" class="appt-book-btn">احجز موعداً</a>
        @else
          <a href="{{ route('login') }}?redirect={{ urlencode(route('appointments.book')) }}" class="appt-book-btn">سجّل الدخول للحجز</a>
        @endauth
      </div>

      <div class="appt-service-card">
        <div class="appt-service-icon">📘</div>
        <h4>جواز السفر</h4>
        <p>إصدار جواز سفر جديد، تجديد، أو استخراج بدل تالف أو فاقد</p>
        <div class="appt-service-meta">
          <span>⏱ مدة الخدمة: 45 دقيقة</span>
          <span>📍 إدارة الجوازات</span>
        </div>
        @auth
          <a href="{{ route('citizen.appointments.index') }}" class="appt-book-btn">احجز موعداً</a>
        @else
          <a href="{{ route('login') }}?redirect={{ urlencode(route('appointments.book')) }}" class="appt-book-btn">سجّل الدخول للحجز</a>
        @endauth
      </div>

      <div class="appt-service-card">
        <div class="appt-service-icon">🚗</div>
        <h4>رخصة القيادة</h4>
        <p>استخراج رخصة قيادة لأول مرة، تجديد، أو تعديل بيانات</p>
        <div class="appt-service-meta">
          <span>⏱ مدة الخدمة: 60 دقيقة</span>
          <span>📍 إدارة المرور</span>
        </div>
        @auth
          <a href="{{ route('citizen.appointments.index') }}" class="appt-book-btn">احجز موعداً</a>
        @else
          <a href="{{ route('login') }}?redirect={{ urlencode(route('appointments.book')) }}" class="appt-book-btn">سجّل الدخول للحجز</a>
        @endauth
      </div>

    </div>
  </div>
</section>

{{-- ===== ملاحظات مهمة ===== --}}
<section class="appt-notes-section">
  <div class="gov-container">
    <div class="appt-note-box">
      <h4>⚠️ ملاحظات مهمة قبل الحجز</h4>
      <ul>
        <li>يجب الحضور في الموعد المحدد بـ <strong>15 دقيقة مبكراً</strong> على الأقل.</li>
        <li>احرص على إحضار <strong>جميع الوثائق المطلوبة</strong> المذكورة في صفحة الخدمة.</li>
        <li>يحق لك تعديل أو إلغاء الموعد قبل <strong>24 ساعة</strong> من وقت الحجز.</li>
        <li>في حال التأخر أكثر من <strong>15 دقيقة</strong>، قد يُلغى موعدك تلقائياً.</li>
      </ul>
    </div>
  </div>
</section>

{{-- ===== دعوة لتسجيل الدخول ===== --}}
@guest
<section class="appt-cta-section">
  <div class="gov-container appt-cta-inner">
    <div>
      <h3>هل لديك حساب؟</h3>
      <p>سجّل الدخول الآن لحجز موعدك والاستمتاع بجميع الخدمات الإلكترونية</p>
    </div>
    <div class="appt-cta-btns">
      <a href="{{ route('login') }}" class="appt-cta-primary">دخول المستخدم</a>
      <a href="{{ route('register') }}" class="appt-cta-secondary">إنشاء حساب جديد</a>
    </div>
  </div>
</section>
@endguest

@endsection

@push('styles')
<style>
/* ===== صفحة حجز المواعيد ===== */
.appt-hero { background: linear-gradient(135deg, #0a4f61 0%, #0d7a96 100%); padding: 48px 0; color: #fff; }
.appt-hero-inner { display: flex; align-items: center; justify-content: space-between; gap: 32px; }
.appt-hero-text h3 { font-size: 26px; margin-bottom: 14px; color: #fff; }
.appt-hero-text p { font-size: 15px; line-height: 1.8; opacity: 0.92; max-width: 560px; }
.appt-benefits { margin-top: 18px; list-style: none; display: flex; flex-direction: column; gap: 8px; }
.appt-benefits li { font-size: 14px; opacity: 0.95; }
.appt-hero-icon { font-size: 90px; opacity: 0.25; flex-shrink: 0; }

/* خطوات الحجز */
.appt-steps-section { padding: 52px 0; background: #f7fcfd; }
.appt-section-title { text-align: center; font-size: 22px; color: #0d7a96; margin-bottom: 36px; }
.appt-section-title::after { content: ''; display: block; width: 60px; height: 3px; background: #d9bd54; margin: 10px auto 0; border-radius: 2px; }
.appt-steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
.appt-step { background: #fff; border-radius: 10px; padding: 28px 20px; text-align: center; box-shadow: 0 2px 12px #0000000d; border-top: 4px solid var(--primary-cyan, #1a9ec0); }
.appt-step-num { font-size: 32px; font-weight: 900; color: var(--primary-cyan, #1a9ec0); margin-bottom: 12px; }
.appt-step h4 { font-size: 15px; color: #1a3a42; margin-bottom: 8px; }
.appt-step p { font-size: 13px; color: #555; line-height: 1.6; }

/* بطاقات الخدمات */
.appt-services-section { padding: 52px 0; background: #fff; }
.appt-services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
.appt-service-card { border: 1px solid #e0e0e0; border-radius: 12px; padding: 32px 24px; text-align: center; transition: box-shadow 0.2s, transform 0.2s; display: flex; flex-direction: column; gap: 12px; }
.appt-service-card:hover { box-shadow: 0 8px 28px #0d7a9622; transform: translateY(-3px); }
.appt-service-icon { font-size: 48px; }
.appt-service-card h4 { font-size: 17px; color: #1a3a42; font-weight: 700; }
.appt-service-card p { font-size: 13px; color: #555; line-height: 1.7; flex: 1; }
.appt-service-meta { display: flex; flex-direction: column; gap: 5px; font-size: 12px; color: #777; background: #f7fcfd; padding: 10px; border-radius: 6px; }
.appt-book-btn { display: inline-block; background: var(--primary-cyan, #1a9ec0); color: #fff; padding: 11px 24px; border-radius: 6px; font-size: 13px; font-weight: 700; margin-top: 4px; transition: background 0.2s; text-decoration: none; }
.appt-book-btn:hover { background: #0d7a96; color: #fff; }

/* ملاحظات */
.appt-notes-section { padding: 40px 0; background: #fffdf4; }
.appt-note-box { border: 2px solid #d9bd54; border-radius: 10px; padding: 28px 32px; background: #fffef8; }
.appt-note-box h4 { font-size: 16px; color: #a07c00; margin-bottom: 14px; }
.appt-note-box ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.appt-note-box ul li { font-size: 14px; color: #444; padding-right: 8px; border-right: 3px solid #d9bd54; }

/* دعوة تسجيل الدخول */
.appt-cta-section { padding: 44px 0; background: linear-gradient(135deg, #050505 0%, #1a3a42 100%); }
.appt-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.appt-cta-inner h3 { font-size: 22px; color: #fff; margin-bottom: 8px; }
.appt-cta-inner p { font-size: 14px; color: #aaa; }
.appt-cta-btns { display: flex; gap: 12px; flex-wrap: wrap; }
.appt-cta-primary { background: var(--primary-cyan, #1a9ec0); color: #fff; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 700; text-decoration: none; transition: background 0.2s; }
.appt-cta-primary:hover { background: #0d7a96; }
.appt-cta-secondary { border: 2px solid #d9bd54; color: #d9bd54; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 700; text-decoration: none; transition: background 0.2s, color 0.2s; }
.appt-cta-secondary:hover { background: #d9bd54; color: #111; }

/* Responsive */
@media (max-width: 900px) {
  .appt-steps-grid { grid-template-columns: repeat(2, 1fr); }
  .appt-services-grid { grid-template-columns: repeat(2, 1fr); }
  .appt-hero-icon { display: none; }
}
@media (max-width: 600px) {
  .appt-steps-grid { grid-template-columns: 1fr; }
  .appt-services-grid { grid-template-columns: 1fr; }
  .appt-cta-inner { flex-direction: column; text-align: center; }
}
</style>
@endpush
