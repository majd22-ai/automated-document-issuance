
@extends('layouts.public')
@section('title', 'إصدار الوثائق - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><h1>إصدار الوثائق</h1><p>إصدار وطباعة الوثائق المعتمدة</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🖨️</div>
    <h3>لا توجد وثائق جاهزة للإصدار</h3>
    <p style="color:var(--text-muted);">ستظهر الوثائق المعتمدة والجاهزة للطباعة هنا.</p>
  </div>
</div></section>
@endsection
