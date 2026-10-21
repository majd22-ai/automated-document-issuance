
@extends('layouts.public')
@section('title', 'التقارير - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>التقارير والإحصائيات</h1><p>تحليل شامل لأداء المنصة</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="grid grid-3">
    <div class="card" style="padding:2rem;text-align:center;">
      <div style="font-size:2.5rem;font-weight:700;color:var(--primary);">0</div>
      <p style="color:var(--text-muted);">إجمالي الطلبات هذا الشهر</p>
    </div>
    <div class="card" style="padding:2rem;text-align:center;">
      <div style="font-size:2.5rem;font-weight:700;color:var(--success,#2ecc71);">0</div>
      <p style="color:var(--text-muted);">وثائق صادرة هذا الشهر</p>
    </div>
    <div class="card" style="padding:2rem;text-align:center;">
      <div style="font-size:2.5rem;font-weight:700;color:var(--warning,#f39c12);">0</div>
      <p style="color:var(--text-muted);">طلبات معلقة</p>
    </div>
  </div>
  <div class="card" style="text-align:center;padding:3rem;margin-top:1rem;">
    <div style="font-size:3rem;margin-bottom:1rem;"><x-icon name="chart" /></div>
    <p style="color:var(--text-muted);">ستظهر الرسوم البيانية والتقارير التفصيلية هنا بعد ربط قاعدة البيانات.</p>
  </div>
</div></section>
@endsection
