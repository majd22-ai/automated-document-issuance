
@extends('layouts.public')
@section('title', 'إعدادات النظام - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إعدادات النظام</h1><p>تكوين وضبط إعدادات منصة الإصدار الآلي</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="grid grid-2">
    <div class="card" style="padding:2rem;">
      <h4 style="margin-bottom:1rem;"><x-icon name="settings" /> الإعدادات العامة</h4>
      <div class="form-group"><label class="form-label">اسم المنصة</label><input type="text" class="form-control" value="منصة الإصدار الآلي" disabled></div>
      <div class="form-group"><label class="form-label">البريد الإلكتروني للدعم</label><input type="email" class="form-control" placeholder="support@platform.gov.ye" disabled></div>
      <button class="btn btn-primary" disabled>حفظ التغييرات</button>
    </div>
    <div class="card" style="padding:2rem;">
      <h4 style="margin-bottom:1rem;"><x-icon name="lock" /> إعدادات الأمان</h4>
      <p style="color:var(--text-muted);font-size:0.9rem;">ستتوفر خيارات الأمان هنا بعد اكتمال التطوير.</p>
    </div>
  </div>
</div></section>
@endsection
