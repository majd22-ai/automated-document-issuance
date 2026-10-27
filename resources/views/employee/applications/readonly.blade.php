@extends('layouts.public')
@section('title','بيانات الطلب ونتيجة الفحص')
@section('content')
<section class="section"><div class="container"><div class="ops-page-head"><h1>بيانات الطلب التشغيلية ونتيجة التحقق</h1><p class="ops-help">اطلع على بيانات الطلب ونتائج التحقق المحفوظة. تتاح إجراءات القرار للموظف المخول بالخدمة.</p></div><div class="card ops-card" style="padding:1.5rem">@include('components.request-details')</div><p>اتخاذ قرارات المراجعة يتطلب صلاحية خدمة مخصصة.</p><a class="btn btn-outline" href="{{ route('employee.dashboard') }}">العودة إلى لوحة العمل</a></div></section>
@endsection
