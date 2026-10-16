
@extends('layouts.public')

@section('title', 'لوحة تحكم الموظف - منصة الإصدار الآلي')

@section('content')

<section class="page-hero">
  <div class="container">
    <h1>لوحة تحكم الموظف</h1>
    <p>مرحباً {{ auth()->user()->name }} — إدارة الطلبات والمراجعات</p>
  </div>
</section>

<section class="section">
  <div class="container">

    <div class="grid grid-4" style="margin-bottom: 2rem;">
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--primary);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">طلبات قيد المراجعة</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--success, #2ecc71);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">طلبات مُعتمدة اليوم</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--warning, #f39c12);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">بانتظار التسليم</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--info, #3498db);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">مُسلّمة هذا الأسبوع</div>
      </div>
    </div>

    <div class="grid grid-3">
      <a href="{{ route('employee.applications.index') }}" class="card card-hover" style="text-align:center; padding:2rem; text-decoration:none;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">📋</div>
        <strong>الطلبات للمراجعة</strong>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.5rem;">مراجعة واعتماد الطلبات المقدمة</p>
      </a>
      <a href="{{ route('employee.citizens.index') }}" class="card card-hover" style="text-align:center; padding:2rem; text-decoration:none;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">👥</div>
        <strong>بيانات المواطنين</strong>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.5rem;">البحث والتحقق من بيانات المواطنين</p>
      </a>
      <a href="{{ route('employee.issuance.index') }}" class="card card-hover" style="text-align:center; padding:2rem; text-decoration:none;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">🖨️</div>
        <strong>إصدار الوثائق</strong>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.5rem;">إصدار وطباعة الوثائق المعتمدة</p>
      </a>
      <a href="{{ route('employee.deliveries.index') }}" class="card card-hover" style="text-align:center; padding:2rem; text-decoration:none;">
        <div style="font-size:2rem; margin-bottom:0.5rem;">📦</div>
        <strong>التسليم والاستلام</strong>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.5rem;">تسجيل عمليات التسليم</p>
      </a>
    </div>

    <div style="margin-top:2rem; text-align:center;">
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="btn btn-outline">تسجيل الخروج</button>
      </form>
    </div>

  </div>
</section>

@endsection
