
@extends('layouts.public')
@section('title', 'مواعيدي - لوحة تحكم المواطن')
@section('content')
<section class="page-hero"><div class="container"><h1>مواعيدي</h1><p>إدارة مواعيد الحضور الشخصي</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('citizen.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📅</div>
    <h3>لا توجد مواعيد مجدولة</h3>
    <p style="color:var(--text-muted);margin:1rem 0;">ستظهر مواعيدك هنا بعد تقديم طلب يتطلب حضوراً شخصياً.</p>
    <a href="{{ route('citizen.applications.create') }}" class="btn btn-primary">تقديم طلب جديد</a>
  </div>
</div></section>
@endsection
