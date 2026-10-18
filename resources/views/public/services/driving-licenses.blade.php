
@extends('layouts.public')

@section('title', 'خدمات رخصة القيادة - منصة الإصدار الآلي')

@section('content')

  {{-- ============ BREADCRUMB ============ --}}
  <div class="container">
    <nav class="breadcrumb">
      <a href="{{ route('home') }}">الرئيسية</a>
      <span class="sep">←</span>
      <span>خدمات رخصة القيادة</span>
    </nav>
  </div>

  {{-- ============ PAGE HERO ============ --}}
  <section class="page-hero">
    <div class="container">
      <h1>خدمات رخصة القيادة</h1>
      <p>استعرض خدمات إصدار وتجديد رخصة القيادة، مع كامل المتطلبات والإجراءات المطلوبة.</p>
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
        <a href="{{ url('/services/driving-licenses/first-issue') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="6" width="20" height="14" rx="2"/>
              <path d="M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
              <circle cx="12" cy="13" r="2"/>
              <path d="M7 20v-2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/>
            </svg>
          </div>
          <div class="content">
            <h4>إصدار رخصة قيادة لأول مرة</h4>
            <p>الحصول على رخصة قيادة يمنية لأول مرة بعد اجتياز الاختبارات المطلوبة.</p>
            <span class="btn btn-outline btn-sm">عرض التفاصيل</span>
          </div>
        </a>

        {{-- Service 2 --}}
        <a href="{{ url('/services/driving-licenses/renewal') }}" class="service-item card-hover">
          <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-3-6.7"/>
              <polyline points="21 3 21 9 15 9"/>
            </svg>
          </div>
          <div class="content">
            <h4>تجديد رخصة القيادة</h4>
            <p>تجديد رخصة القيادة المنتهية الصلاحية أو التي تقترب من انتهائها.</p>
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
          <li>أن يكون عمر المتقدم 18 سنة أو أكثر لرخصة القيادة العادية.</li>
          <li>تقديم شهادة اللياقة الطبية من مستشفى أو مركز صحي معتمد.</li>
          <li>بطاقة الهوية الشخصية سارية المفعول.</li>
          <li>صورتان شخصيتان حديثتان بخلفية بيضاء.</li>
          <li>سداد الرسوم المقررة وفق نوع الخدمة.</li>
          <li>اجتياز الاختبارات النظرية والعملية (للإصدار الأول).</li>
        </ul>
      </div>
    </div>
  </section>

@include('components.service-requirements', ['category' => 'driving-licenses'])
@endsection
