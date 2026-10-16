
@extends('layouts.public')

@section('title', $service->name . ' - منصة الإصدار الآلي')

@section('content')

  {{-- BREADCRUMB --}}
  <div class="container">
    <nav class="breadcrumb">
      <a href="{{ route('home') }}">الرئيسية</a>
      <span class="sep">←</span>
      <a href="{{ $service->category_url }}">{{ $service->category_name }}</a>
      <span class="sep">←</span>
      <span>{{ $service->name }}</span>
    </nav>
  </div>

  {{-- PAGE HERO --}}
  <section class="page-hero">
    <div class="container">
      <h1>{{ $service->name }}</h1>
      <p>{{ $service->short_description }}</p>
    </div>
  </section>

  {{-- SERVICE DETAILS --}}
  <section class="section">
    <div class="container">
      <div class="grid service-details-grid">

        {{-- MAIN CONTENT --}}
        <div>

          {{-- Description --}}
          <div class="card mb-3">
            <h3 class="text-cyan mb-2">وصف الخدمة</h3>
            <p>{{ $service->description }}</p>
          </div>

          {{-- Eligibility --}}
          <div class="card mb-3">
            <h3 class="text-cyan mb-2">شروط الأهلية</h3>
            <ul class="requirements-list">
              @foreach($service->eligibility as $item)
                <li>{{ $item }}</li>
              @endforeach
            </ul>
          </div>

          {{-- Required Documents --}}
          <div class="card mb-3">
            <h3 class="text-cyan mb-2">المستندات المطلوبة</h3>
            <ul class="requirements-list">
              @foreach($service->documents as $item)
                <li>{{ $item }}</li>
              @endforeach
            </ul>
          </div>

          {{-- Procedure --}}
          <div class="card mb-3">
            <h3 class="text-cyan mb-2">خطوات تنفيذ الخدمة</h3>
            <ol style="padding-right: 20px; line-height: 2.2;">
              @foreach($service->steps as $step)
                <li>{{ $step }}</li>
              @endforeach
            </ol>
          </div>

        </div>

        {{-- SIDEBAR --}}
        <aside>
          <div class="card" style="position: sticky; top: 90px;">
            <h4 class="mb-3">معلومات الخدمة</h4>

            <div class="info-grid" style="grid-template-columns: 1fr;">
              <div class="info-item">
                <div class="label">الرسوم</div>
                <div class="value">{{ $service->fees }}</div>
              </div>
              <div class="info-item">
                <div class="label">مدة الإنجاز</div>
                <div class="value">{{ $service->duration }}</div>
              </div>
              <div class="info-item">
                <div class="label">هل يتطلب موعد؟</div>
                <div class="value">{{ $service->requires_appointment ? 'نعم' : 'لا' }}</div>
              </div>
            </div>

            @auth
              @if(auth()->user()->role === 'citizen')
                <a href="{{ $service->apply_url }}"
                   class="btn btn-primary btn-block btn-lg mt-3">
                  بدء تقديم الطلب
                </a>
              @else
                <p class="text-muted mt-3" style="font-size:12px; text-align:center;">
                  تقديم الطلبات متاح لحسابات المواطنين فقط.
                </p>
              @endif
            @else
              <a href="{{ url('/login?redirect=' . urlencode($service->apply_url)) }}"
                 class="btn btn-primary btn-block btn-lg mt-3">
                بدء تقديم الطلب
              </a>

              <p class="text-muted mt-2" style="font-size:12px; text-align:center;">
                سيتم توجيهك لتسجيل الدخول أولًا
              </p>
            @endauth
          </div>
        </aside>

      </div>
    </div>
  </section>

@endsection
