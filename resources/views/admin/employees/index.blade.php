
@extends('layouts.public')
@section('title', 'إدارة الموظفين - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إدارة الموظفين</h1><p>عرض وإدارة حسابات الموظفين</p></div></section>
<section class="section"><div class="container">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a>
    <button class="btn btn-primary">+ إضافة موظف جديد</button>
  </div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">👔</div>
    <h3>لا يوجد موظفون مسجلون</h3>
    <p style="color:var(--text-muted);">ستظهر قائمة الموظفين هنا بعد ربط قاعدة البيانات.</p>
  </div>
</div></section>
@endsection
