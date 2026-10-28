@extends('layouts.public')
@section('title', $title.' - منصة الإصدار الآلي')
@section('content')
<section class="page-hero"><div class="container"><h1>{{ $title }}</h1><p>الخدمات المتاحة وفق الإعدادات المعتمدة في المنصة</p></div></section>
<section class="section" id="requirements"><div class="container">
  <div class="grid grid-2">
    @forelse($services as $service)
      <a href="{{ $service->publicUrl() }}" class="service-item card-hover">
        <div class="icon-box"><x-icon :name="['identity' => 'identity', 'passport' => 'passport', 'driving_license' => 'car'][$service->document_type]" size="30" /></div>
        <div class="content"><h2>{{ $service->name }}</h2><p>{{ $service->description ?? 'الوصف قيد الاعتماد.' }}</p><span>{{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</span><p>الرسوم: {{ $service->fee !== null ? $service->fee.' '.$service->currency : 'لم تُحدّد' }}</p><span class="btn btn-outline btn-sm">الشروط والتفاصيل</span></div>
      </a>
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
