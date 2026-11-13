@extends('layouts.public')
@section('title', 'مركز أنظمة المحاكاة')
@section('content')
<section class="ops-section"><div class="container">
  <div class="ops-page-head"><h1>مركز أنظمة المحاكاة</h1><p>مدخل موحّد للأنظمة التجريبية في المنصة.</p></div>
  <div class="card ops-card" role="note"><strong>محاكاة تعليمية — لا تتصل بجهات رسمية</strong><p>السجلات والأسماء والنتائج هنا تجريبية ولا تثبت صحة بيانات حكومية أو اعتماد فروع بريد رسمية. فتح المركز لا يفعّل الأنظمة ولا يغيّر الصلاحيات.</p></div>
  <div class="ops-grid">
    @foreach($systems as $system)
      <article class="card ops-card">
        <h2>{{ $system['title'] }}</h2>
        <p><strong>الحالة: {{ ! $system['enabled'] ? 'غير مفعّل' : ($system['ready'] ? 'مفعّل' : 'تحتاج تهيئة') }}</strong></p>
        <p>{{ $system['description'] }}</p>
        @if($system['ready'])
          <a class="btn btn-primary" href="{{ route($system['route']) }}">فتح {{ $system['title'] }}</a>
          @if($system['secondary_route'] ?? null)<p><a class="btn btn-outline" href="{{ route($system['secondary_route']) }}">المراجعة الإدارية التجريبية</a></p>@endif
        @elseif($system['enabled'])
          <p>لم تكتمل تهيئة جداول هذا النظام. يلزم استكمال التهيئة المعتمدة قبل استخدامه.</p>
        @else
          <p>غير متاح في بيئة التشغيل أو إعداداتها الحالية. يلزم تفعيله بالإعدادات المخصصة له.</p>
        @endif
      </article>
    @endforeach
  </div>
  <div class="card ops-card"><h2>مراقبة التكامل الإلكتروني</h2><p>لمراجعة حالة مزوّدي التحقق وسجل المحاولات ونتائجها وأخطائها، استخدم صفحة التكامل الإلكتروني.</p><a class="btn btn-outline" href="{{ route('admin.integrations.index') }}">فتح التكامل الإلكتروني</a></div>
</div></section>
@endsection
