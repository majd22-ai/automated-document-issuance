
@extends('layouts.public')
@section('title', 'تقديم طلب جديد - لوحة تحكم المواطن')
@section('content')
<section class="page-hero">
  <div class="container">
    <h1>تقديم طلب جديد</h1>
    <p>اختر نوع الخدمة التي تريد تقديم طلب لها</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div style="margin-bottom:1.5rem;">
      <a href="{{ route('citizen.applications.index') }}" style="color:var(--primary); text-decoration:none;">← العودة للطلبات</a>
    </div>

    @php $service = request('service', ''); @endphp

    <div class="section-header">
      <h2>اختر الخدمة المطلوبة</h2>
    </div>

    <div class="grid grid-3">
      {{-- البطاقة الشخصية --}}
      <div class="card" style="padding:1.5rem;">
        <h4 style="margin-bottom:1rem; color:var(--primary);">▣ البطاقة الشخصية</h4>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <a href="{{ url('/services/identity/first-issue') }}" class="btn btn-outline btn-sm">إصدار لأول مرة</a>
          <a href="{{ url('/services/identity/renewal') }}" class="btn btn-outline btn-sm">تجديد البطاقة</a>
          <a href="{{ url('/services/identity/lost-replacement') }}" class="btn btn-outline btn-sm">بدل فاقد</a>
        </div>
      </div>

      {{-- جواز السفر --}}
      <div class="card" style="padding:1.5rem;">
        <h4 style="margin-bottom:1rem; color:var(--primary);">▤ جواز السفر</h4>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <a href="{{ url('/services/passports/first-issue') }}" class="btn btn-outline btn-sm">إصدار لأول مرة</a>
          <a href="{{ url('/services/passports/renewal') }}" class="btn btn-outline btn-sm">تجديد الجواز</a>
        </div>
      </div>

      {{-- رخصة القيادة --}}
      <div class="card" style="padding:1.5rem;">
        <h4 style="margin-bottom:1rem; color:var(--primary);">◉ رخصة القيادة</h4>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <a href="{{ url('/services/driving-licenses/first-issue') }}" class="btn btn-outline btn-sm">إصدار لأول مرة</a>
          <a href="{{ url('/services/driving-licenses/renewal') }}" class="btn btn-outline btn-sm">تجديد الرخصة</a>
        </div>
      </div>
    </div>

    <div class="card" style="padding:1.5rem; margin-top:2rem; background:var(--background-soft);">
      <p style="color:var(--text-muted); font-size:0.9rem;">
        ℹ️ لتقديم طلب، اضغط على نوع الخدمة لعرض تفاصيلها ومتطلباتها كاملاً، ثم اضغط "بدء تقديم الطلب" من صفحة تفاصيل الخدمة.
      </p>
    </div>
  </div>
</section>
@endsection
