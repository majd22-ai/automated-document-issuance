
@extends('layouts.public')

@section('title', 'خدمات جواز السفر - منصة الإصدار الآلي')

@section('content')

  {{-- ============ BREADCRUMB ============ --}}
  <div class="container">
    <nav class="breadcrumb">
      <a href="{{ route('home') }}">الرئيسية</a>
      <span class="sep">←</span>
      <span>خدمات جواز السفر</span>
    </nav>
  </div>

  {{-- ============ PAGE HERO ============ --}}
  <section class="page-hero">
    <div class="container">
      <h1>خدمات جواز السفر</h1>
      <p>استعرض خدمات إصدار وتجديد جواز السفر اليمني، مع كامل المتطلبات والإجراءات المطلوبة.</p>
    </div>
  </section>

  {{-- ============ SERVICES LIST ============ --}}
  <section class="section">
    <div class="container">
      <div class="section-header">
        <h2>الخدمات المتاحة</h2>
        <p>اختر الخدمة التي ترغب في الاستفادة منها</p>
      </div>

      <div class="grid grid-2">

        {{-- Service 1 --}}
        <a href="{{ url('/services/passports/first-issue') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="2" width="16" height="20" rx="2"/>
              <path d="M9 10h6M9 14h4"/>
              <circle cx="12" cy="7" r="2"/>
            </svg>
          </div>
          <div class="content">
            <h4>إصدار جواز سفر لأول مرة</h4>
            <p>استخراج جواز سفر يمني لأول مرة لجميع الفئات العمرية.</p>
            <span class="btn btn-outline btn-sm">عرض التفاصيل</span>
          </div>
        </a>

        {{-- Service 2 --}}
        <a href="{{ url('/services/passports/renewal') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-3-6.7"/>
              <polyline points="21 3 21 9 15 9"/>
            </svg>
          </div>
          <div class="content">
            <h4>تجديد جواز السفر</h4>
            <p>تجديد جواز السفر المنتهية صلاحيته أو القريب من الانتهاء.</p>
            <span class="btn btn-outline btn-sm">عرض التفاصيل</span>
          </div>
        </a>

      </div>
    </div>
  </section>

  {{-- ============ GENERAL REQUIREMENTS ============ --}}
  <section class="section" style="background: var(--background-soft);">
    <div class="container">
      <div class="section-header">
        <h2>المتطلبات العامة</h2>
        <p>يجب على جميع مقدمي الطلبات الالتزام بالمتطلبات التالية</p>
      </div>
      <div class="card" style="max-width: 800px; margin: 0 auto;">
        <ul class="requirements-list">
          <li>أن يكون مقدم الطلب يمني الجنسية.</li>
          <li>تقديم بطاقة الهوية الشخصية سارية المفعول.</li>
          <li>صورة شخصية حديثة وفق مواصفات جوازات السفر الدولية.</li>
          <li>شهادة الميلاد أو مستخرج رسمي للإصدار الأول.</li>
          <li>سداد الرسوم المقررة وفق نوع الخدمة.</li>
          <li>الحضور الشخصي لاستكمال التحقق البيومتري.</li>
        </ul>
      </div>
    </div>
  </section>

@endsection
