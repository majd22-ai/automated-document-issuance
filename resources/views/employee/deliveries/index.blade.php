
@extends('layouts.public')
@section('title', 'التسليم - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><h1>التسليم والاستلام</h1><p>تسجيل عمليات تسليم الوثائق للمواطنين</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.dashboard') }}" style="color:var(--primary);text-decoration:none;">← العودة للوحة التحكم</a></div>
  <div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📦</div>
    <h3>لا توجد عمليات تسليم اليوم</h3>
    <p style="color:var(--text-muted);">ستظهر عمليات التسليم المجدولة هنا.</p>
  </div>
</div></section>
@endsection
