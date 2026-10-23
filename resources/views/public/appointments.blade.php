@extends('layouts.public')
@section('title', 'حجز المواعيد - منصة الإصدار الآلي')
@section('content')
    <section class="section"><div class="container"><div class="card" style="padding:1.5rem"><h1>حجز مواعيد الخدمات</h1><p>الحجز الفعلي مرتبط بطلب خدمة موجود. افتح الطلب ثم اختر «المواعيد والفحوصات والرسوم» للاطلاع على فترات المركز المتاحة.</p>
    @auth
      @if(auth()->user()->role==='citizen')<a class="btn btn-primary" href="{{ route('citizen.applications.index') }}">اختيار طلب لحجز موعد</a> <a class="btn btn-outline" href="{{ route('citizen.appointments.index') }}">مواعيدي</a>
      @elseif(auth()->user()->role==='employee')<a class="btn btn-primary" href="{{ route('employee.appointments.index') }}">إدارة المواعيد</a>@endif
    @else<a class="btn btn-primary" href="{{ route('login') }}">تسجيل الدخول لحجز موعد</a>@endauth
    </div></div></section>
    <details class="container"><summary>معاينة تجريبية مستقلة — لا تنشئ حجزًا فعليًا</summary>@include('components.portal-demo', ['screen' => 'appointments'])</details>
@endsection
