@extends('layouts.public')
@section('title', $title.' - منصة الإصدار الآلي')
@section('content')
<section class="page-hero"><div class="container"><h1>{{ $title }}</h1><p>الخدمات المتاحة وفق الإعدادات المعتمدة في المنصة</p></div></section>
<section class="section" id="requirements"><div class="container">
  <div class="grid grid-2">
    @forelse($services as $service)
      @php
        $serviceIcon = match ($service->code) {
            'identity-first-issue', 'identity-renewal', 'identity-lost',
            'passport-first-issue', 'passport-renewal',
            'license-first-issue', 'license-renewal',
            'birth-certificate-first-issue' => $service->code,
            default => ['identity' => 'identity', 'passport' => 'passport', 'driving_license' => 'car', 'birth_certificate' => 'file'][$service->document_type] ?? 'file',
        };
        $serviceIconSprite = str_contains($serviceIcon, '-') ? 'service' : 'outline';
        $startPath = $service->code === 'identity-first-issue'
            ? route('citizen.identity-card.start')
            : route('citizen.applications.create', ['service' => $service->code]);
        $startUrl = auth()->check() && auth()->user()->role === 'citizen'
            ? $startPath
            : route('login', ['redirect' => $startPath]);
      @endphp
      <article class="service-item card-hover">
        <div class="icon-box"><x-icon :name="$serviceIcon" :sprite="$serviceIconSprite" size="30" /></div>
        <div class="content">
          <h2>
            @if($service->rules_approved && (!auth()->check() || auth()->user()->role === 'citizen'))
              <a href="{{ $startUrl }}">{{ $service->name }}</a>
            @else
              {{ $service->name }}
            @endif
          </h2>
          <div class="service-actions">
            @auth
              @if(auth()->user()->role === 'citizen')
                @if($service->rules_approved)
                  <a class="service-start-button" href="{{ $startPath }}">بدء الخدمة أو متابعة المسودة</a>
                @else
                  <p class="alert alert-info" role="status">الخدمة معروضة للاطلاع فقط؛ بدء الطلب بانتظار اعتماد متطلباتها.</p>
                @endif
              @else
                <p class="alert alert-info" role="status">بدء الخدمة متاح من حساب المواطن.</p>
              @endif
            @else
              @if($service->rules_approved)
                <a class="service-start-button" href="{{ $startUrl }}">تسجيل الدخول وبدء الخدمة</a>
              @else
                <p class="alert alert-info" role="status">الخدمة معروضة للاطلاع فقط؛ بدء الطلب بانتظار اعتماد متطلباتها.</p>
              @endif
            @endauth
            <a class="service-details-link" href="{{ $service->publicUrl() }}">
              <x-icon name="file" />
              <span>الشروط والتفاصيل</span>
              <x-icon name="arrow-left" />
            </a>
          </div>
        </div>
      </article>
    @empty
      <div class="card"><p>لا توجد خدمات مفعّلة حاليًا. تُتاح الخدمات بعد ضبط بياناتها واعتمادها من الجهة المختصة.</p></div>
    @endforelse
  </div>
  {{ $services->links() }}
</div></section>
@if(request()->route('documentType') === 'identity' || !request()->route('documentType'))
@include('components.civil-registry-links')
@endif
@endsection
