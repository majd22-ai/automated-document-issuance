
@extends('layouts.public')
@section('title', 'إدارة الأدوار - لوحة المدير')
@section('content')
<section class="ops-page-head"><div class="container"><h1>إدارة الأدوار والصلاحيات</h1><p>تعريف أدوار المستخدمين وصلاحياتهم</p></div></section>
<section class="ops-section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="card ops-card ops-help"><p>الأدوار التالية معرفة في النظام. يحدد دور الحساب من إدارة المستخدمين، وتمنح صلاحيات الموظف لكل خدمة بشكل مستقل.</p><div class="ops-toolbar"><a class="btn btn-primary" href="{{ route('admin.users.index') }}">إدارة أدوار الحسابات</a><a class="btn btn-outline" href="{{ route('admin.review-permissions.index') }}">صلاحيات الخدمات</a></div></div><div class="ops-grid">
    <div class="card ops-card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="user" /></div><h2>مواطن</h2><p style="color:var(--text-muted);font-size:0.85rem;">تقديم الطلبات ومتابعتها</p>
    </div>
    <div class="card ops-card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="briefcase" /></div><h2>موظف</h2><p style="color:var(--text-muted);font-size:0.85rem;">معالجة الطلبات وفق الصلاحيات الممنوحة لكل خدمة</p>
    </div>
    <div class="card ops-card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:2rem;"><x-icon name="key" /></div><h2>مدير</h2><p style="color:var(--text-muted);font-size:0.85rem;">إدارة كاملة للنظام والمستخدمين</p>
    </div>
  </div>
</div></section>
@endsection
