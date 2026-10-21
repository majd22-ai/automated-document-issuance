
@extends('layouts.public')
@section('title', 'بيانات المواطنين - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><h1>بيانات المواطنين</h1><p>البحث والتحقق من بيانات المواطنين</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="card" style="padding:2rem;">
    <div class="form-group" style="max-width:500px;">
      <label class="form-label">البحث برقم الوطني أو الاسم</label>
      <input type="text" class="form-control" placeholder="أدخل الرقم الوطني أو الاسم...">
    </div>
    <button class="btn btn-primary">بحث</button>
  </div>
  <div class="card" style="text-align:center;padding:3rem;margin-top:1rem;">
    <div style="font-size:3rem;margin-bottom:1rem;"><x-icon name="users" /></div>
    <p style="color:var(--text-muted);">ستظهر نتائج البحث هنا.</p>
  </div>
</div></section>
@endsection
