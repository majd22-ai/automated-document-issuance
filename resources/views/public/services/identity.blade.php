
@extends('layouts.public')

@section('title', 'خدمات البطاقة الشخصية - منصة الإصدار الآلي')

@section('content')

  {{-- ============ BREADCRUMB ============ --}}
  <div class="container">
    <nav class="breadcrumb">
      <a href="{{ route('home') }}">الرئيسية</a>
      <span class="sep"><x-icon name="arrow-left" /></span>
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
            <x-icon name="identity" size="30" />
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
            <x-icon name="refresh" size="30" />
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
            <x-icon name="replace" size="30" />
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
@endsection
