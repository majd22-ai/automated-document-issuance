
@extends('layouts.public')
@section('title', 'وثائقي - لوحة تحكم المواطن')
@section('content')
<section class="page-hero"><div class="container"><h1>وثائقي</h1><p>جميع الوثائق الجاهزة للاستلام</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('citizen.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📄</div>
    <h3>لا توجد وثائق جاهزة</h3>
    <p style="color:var(--text-muted);margin:1rem 0;">ستظهر وثائقك الجاهزة هنا بعد اعتماد طلباتك.</p>
    <a href="{{ route('citizen.applications.index') }}" class="btn btn-primary">عرض طلباتي</a>
  </div>
</div></section>
@endsection
