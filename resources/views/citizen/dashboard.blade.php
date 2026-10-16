
@extends('layouts.public')

@section('title', 'لوحة تحكم المواطن - منصة الإصدار الآلي')

@section('content')

{{-- ======= HERO ======= --}}
<section class="gov-page-title" aria-label="لوحة التحكم">
  <div class="gov-container">
    <h2>مرحباً، {{ auth()->user()->name }}</h2>
    <p style="color:#62686c; font-size:14px; margin-top:4px;">تابع طلباتك ومواعيدك ووثائقك من هنا</p>
  </div>
</section>

<div class="gov-container">
  <div class="gold-divider" style="margin: 28px 0 0;"><span class="diamond"></span></div>
</div>

<section class="section" style="background:#f8fbfc;">
  <div class="gov-container">

    {{-- إحصائيات سريعة --}}
    <div class="section-header" style="margin-bottom:28px;">
      <h2>إحصائياتي</h2>
    </div>
    <div class="grid grid-4" style="margin-bottom:56px;">
      <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value">0</div>
        <div class="stat-label">طلبات نشطة</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value">0</div>
        <div class="stat-label">طلبات مكتملة</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value">0</div>
        <div class="stat-label">مواعيد قادمة</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📄</div>
        <div class="stat-value">0</div>
        <div class="stat-label">وثائق جاهزة</div>
      </div>
    </div>

    {{-- بوابة الخدمات --}}
    <div class="section-header" style="margin-bottom:28px;">
      <h2>تقديم طلب جديد</h2>
      <p>اختر نوع الخدمة للاطلاع على تفاصيلها وتقديم طلبك</p>
    </div>
    <div class="gov-services-grid" style="margin-bottom:56px;">
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

    {{-- روابط إدارة الحساب --}}
    <div class="section-header" style="margin-bottom:28px;">
      <h2>إدارة حسابي</h2>
      <p>راجع طلباتك ومواعيدك ووثائقك ومدفوعاتك</p>
    </div>
    <div class="grid grid-4" style="margin-bottom:40px;">
      <a href="{{ route('citizen.applications.index') }}" class="quick-card">
        <div class="quick-icon">📋</div>
        <h4>طلباتي</h4>
        <p>تتبع حالة جميع الطلبات المقدمة</p>
      </a>
      <a href="{{ route('citizen.appointments.index') }}" class="quick-card">
        <div class="quick-icon">📅</div>
        <h4>مواعيدي</h4>
        <p>إدارة مواعيد الحضور الشخصي</p>
      </a>
      <a href="{{ route('citizen.documents.index') }}" class="quick-card">
        <div class="quick-icon">📄</div>
        <h4>وثائقي</h4>
        <p>الوثائق الجاهزة للاستلام</p>
      </a>
      <a href="{{ route('citizen.payments.index') }}" class="quick-card">
        <div class="quick-icon">💳</div>
        <h4>مدفوعاتي</h4>
        <p>سجل عمليات الدفع</p>
      </a>
    </div>

    {{-- زر تسجيل الخروج --}}
    <div class="logout-strip">
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="btn btn-outline">⬅ تسجيل الخروج</button>
      </form>
    </div>

  </div>
</section>

@endsection
