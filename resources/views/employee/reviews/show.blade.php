
@extends('layouts.public')
@section('title', 'مراجعة الطلب - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><h1>مراجعة الطلب</h1><p>تفاصيل الطلب رقم #{{ $applicationId }}</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.applications.index') }}" style="color:var(--primary);text-decoration:none;">← العودة للطلبات</a></div>
  <div class="card" style="padding:2rem;text-align:center;">
    <p style="color:var(--text-muted);">الطلب رقم #{{ $applicationId }} — ستظهر التفاصيل هنا عند ربط قاعدة البيانات.</p>
  </div>
</div></section>
@endsection
