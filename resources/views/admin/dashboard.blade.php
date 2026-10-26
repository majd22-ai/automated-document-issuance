
@extends('layouts.public')

@section('title', 'لوحة تحكم المدير - منصة الإصدار الآلي')

@section('content')

<section class="page-hero">
  <div class="container">
    <h1>لوحة تحكم المدير</h1>
    <p><a href="{{ route('admin.integrations.index') }}">إعدادات التكامل وسجل التحقق الإلكتروني</a></p>
    <p>مرحباً {{ auth()->user()->name }} — إدارة شاملة للنظام</p>
  </div>
</section>

<section class="section">
  <div class="container">

    <div class="grid grid-4" style="margin-bottom: 2rem;">
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--primary);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">إجمالي المستخدمين</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--success, #2ecc71);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">طلبات هذا الشهر</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--warning, #f39c12);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">موظفون نشطون</div>
      </div>
      <div class="card" style="text-align:center; padding:1.5rem;">
        <div style="font-size:2rem; font-weight:700; color:var(--info, #3498db);">0</div>
        <div style="font-size:0.9rem; color:var(--text-muted);">وثائق مُصدَرة</div>
      </div>
    </div>

    <div class="grid grid-4">
      <a href="{{ route('admin.users.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="user" /></div>
        <strong>إدارة المستخدمين</strong>
      </a>
      <a href="{{ route('admin.employees.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="briefcase" /></div>
        <strong>إدارة الموظفين</strong>
      </a>
      <a href="{{ route('admin.reports.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="chart" /></div>
        <strong>التقارير والإحصائيات</strong>
      </a>
      <a href="{{ route('admin.roles.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="shield" /></div>
        <strong>إدارة الأدوار</strong>
      </a>
      <a href="{{ route('admin.audit-logs.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="file" /></div>
        <strong>سجلات النظام</strong>
      </a>
      <a href="{{ route('admin.settings.index') }}" class="card card-hover" style="text-align:center; padding:1.5rem; text-decoration:none;">
        <div style="font-size:1.8rem; margin-bottom:0.5rem;"><x-icon name="settings" /></div>
        <strong>إعدادات النظام</strong>
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
