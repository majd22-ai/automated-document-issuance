
@extends('layouts.public')
@section('title', 'سجلات النظام - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>سجلات النظام</h1><p>مراقبة وتتبع جميع العمليات على المنصة</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📝</div>
    <h3>لا توجد سجلات</h3>
    <p style="color:var(--text-muted);">ستظهر سجلات نشاط النظام هنا بعد ربط قاعدة البيانات.</p>
  </div>
</div></section>
@endsection
