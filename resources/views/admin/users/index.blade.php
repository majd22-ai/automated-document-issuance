
@extends('layouts.public')
@section('title', 'إدارة المستخدمين - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إدارة المستخدمين</h1><p>عرض وإدارة جميع حسابات المستخدمين</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="padding:2rem;">
    <div class="form-group" style="max-width:500px;">
      <input type="text" class="form-control" placeholder="البحث عن مستخدم...">
    </div>
    <button class="btn btn-primary">بحث</button>
  </div>
  <div class="card" style="text-align:center;padding:3rem;margin-top:1rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">👤</div>
    <p style="color:var(--text-muted);">ستظهر قائمة المستخدمين هنا بعد ربط قاعدة البيانات.</p>
  </div>
</div></section>
@endsection
