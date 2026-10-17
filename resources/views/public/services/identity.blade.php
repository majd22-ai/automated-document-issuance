
@extends('layouts.public')

@section('title', 'خدمات البطاقة الشخصية - منصة الإصدار الآلي')

@section('content')

  {{-- ============ BREADCRUMB ============ --}}
  <div class="container">
    <nav class="breadcrumb">
      <a href="{{ route('home') }}">الرئيسية</a>
      <span class="sep">←</span>
      <span>خدمات البطاقة الشخصية</span>
    </nav>
  </div>

  {{-- ============ PAGE HERO ============ --}}
  <section class="page-hero">
    <div class="container">
      <h1>خدمات البطاقة الشخصية</h1>
      <p>استعرض خدمات إصدار وتجديد واستبدال بطاقة الهوية الشخصية، مع كامل المتطلبات والإجراءات المطلوبة.</p>
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
        <a href="{{ url('/services/identity/first-issue') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <circle cx="8" cy="11" r="2.5"/>
              <path d="M3.5 18c.8-2.4 2.6-3.5 4.5-3.5s3.7 1.1 4.5 3.5"/>
            </svg>
          </div>
          <div class="content">
            <h4>إصدار بطاقة شخصية لأول مرة</h4>
            <p>خدمة إصدار بطاقة الهوية الشخصية لأول مرة للمواطنين الذين بلغوا السن القانونية.</p>
            <span class="btn btn-outline btn-sm">عرض التفاصيل</span>
          </div>
        </a>

        {{-- Service 2 --}}
        <a href="{{ url('/services/identity/renewal') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-3-6.7"/>
              <polyline points="21 3 21 9 15 9"/>
            </svg>
          </div>
          <div class="content">
            <h4>تجديد البطاقة الشخصية</h4>
            <p>تجديد بطاقة الهوية الشخصية قبل انتهاء صلاحيتها مع الاحتفاظ بنفس الرقم الوطني.</p>
            <span class="btn btn-outline btn-sm">عرض التفاصيل</span>
          </div>
        </a>

        {{-- Service 3 --}}
        <a href="{{ url('/services/identity/lost-replacement') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2v4"/>
              <path d="M5 6l-2 3 3 1"/>
              <circle cx="12" cy="14" r="6"/>
              <line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
          </div>
          <div class="content">
            <h4>إصدار بدل فاقد للبطاقة الشخصية</h4>
            <p>إصدار بدل فاقد عند فقدان البطاقة، مع تسجيل بلاغ الفقدان ومتابعة الطلب.</p>
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
          <li>تقديم صورة من شهادة الميلاد أو مستخرج رسمي.</li>
          <li>صورة شخصية حديثة بخلفية بيضاء.</li>
          <li>تقديم الأصل للمراجعة عند الحضور الشخصي.</li>
          <li>سداد الرسوم المقررة وفق نوع الخدمة.</li>
          <li>استكمال البيانات الشخصية بشكل دقيق في الطلب الإلكتروني.</li>
        </ul>
      </div>
    </div>
  </section>
@include('components.service-requirements', ['category' => 'identity'])
<!-- @endsection -->
