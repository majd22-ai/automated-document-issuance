
@extends('layouts.public')
@section('title', 'إدارة الأدوار - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إدارة الأدوار والصلاحيات</h1><p>تعريف أدوار المستخدمين وصلاحياتهم</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="grid grid-3">
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="user" /></div><h4>مواطن</h4><p style="color:var(--text-muted);font-size:0.85rem;">تقديم الطلبات ومتابعتها</p>
    </div>
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="briefcase" /></div><h4>موظف</h4><p style="color:var(--text-muted);font-size:0.85rem;">مراجعة الطلبات وإصدار الوثائق</p>
    </div>
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="key" /></div><h4>مدير</h4><p style="color:var(--text-muted);font-size:0.85rem;">إدارة كاملة للنظام والمستخدمين</p>
    </div>
  </div>
</div></section>
@endsection
