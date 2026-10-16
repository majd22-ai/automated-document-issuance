
@extends('layouts.public')
@section('title', 'الطلبات - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><h1>الطلبات للمراجعة</h1><p>مراجعة واعتماد طلبات المواطنين</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
    <h3>لا توجد طلبات معلقة</h3>
    <p style="color:var(--text-muted);margin:1rem 0;">ستظهر الطلبات المقدمة هنا فور تقديمها من المواطنين.</p>
  </div>
</div></section>
@endsection
