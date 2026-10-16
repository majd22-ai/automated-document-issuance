
@extends('layouts.public')
@section('title', 'إشعاراتي - لوحة تحكم المواطن')
@section('content')
<section class="page-hero"><div class="container"><h1>الإشعارات</h1><p>متابعة جميع تحديثات طلباتك</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('citizen.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🔔</div>
    <h3>لا توجد إشعارات</h3>
    <p style="color:var(--text-muted);margin:1rem 0;">ستصلك الإشعارات هنا عند تحديث حالة طلباتك.</p>
  </div>
</div></section>
@endsection
