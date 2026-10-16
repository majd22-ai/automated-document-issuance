
@extends('layouts.public')
@section('title', 'طلباتي - لوحة تحكم المواطن')
@section('content')
<section class="page-hero">
  <div class="container">
    <h1>طلباتي</h1>
    <p>تتبع حالة جميع طلباتك المقدمة</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
      <div>
        <a href="{{ route('citizen.dashboard') }}" style="color:var(--primary); text-decoration:none;">← العودة للوحة التحكم</a>
      </div>
      <a href="{{ route('citizen.applications.create') }}" class="btn btn-primary">+ تقديم طلب جديد</a>
    </div>
    <div class="card" style="text-align:center; padding:3rem;">
      <div style="font-size:3rem; margin-bottom:1rem;">📋</div>
      <h3>لا توجد طلبات بعد</h3>
      <p style="color:var(--text-muted); margin:1rem 0;">لم تقدم أي طلب حتى الآن. اضغط على الزر أعلاه لتقديم طلبك الأول.</p>
      <a href="{{ route('citizen.applications.create') }}" class="btn btn-primary">تقديم طلب جديد</a>
    </div>
  </div>
</section>
@endsection
