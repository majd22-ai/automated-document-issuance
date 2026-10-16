
@extends('layouts.public')
@section('title', 'إدارة الخدمات - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إدارة الخدمات</h1><p>تكوين وإدارة الخدمات المتاحة على المنصة</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="grid grid-3">
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;">▣</div><h4>البطاقة الشخصية</h4>
      <p style="color:var(--text-muted);font-size:0.85rem;">3 خدمات مفعّلة</p>
      <a href="{{ url('/services/identity') }}" class="btn btn-outline btn-sm" style="margin-top:0.5rem;">عرض</a>
    </div>
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;">▤</div><h4>جواز السفر</h4>
      <p style="color:var(--text-muted);font-size:0.85rem;">خدمتان مفعّلتان</p>
      <a href="{{ url('/services/passports') }}" class="btn btn-outline btn-sm" style="margin-top:0.5rem;">عرض</a>
    </div>
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;">◉</div><h4>رخصة القيادة</h4>
      <p style="color:var(--text-muted);font-size:0.85rem;">خدمتان مفعّلتان</p>
      <a href="{{ url('/services/driving-licenses') }}" class="btn btn-outline btn-sm" style="margin-top:0.5rem;">عرض</a>
    </div>
  </div>
</div></section>
@endsection
