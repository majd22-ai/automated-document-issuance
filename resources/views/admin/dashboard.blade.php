@extends('layouts.public')
@section('title', 'لوحة الإدارة')
@section('content')
<section class="ops-section"><div class="container">
<div class="ops-page-head"><h1>إدارة المنصة</h1><p>مرحبًا {{ auth()->user()->name }}، اختر مساحة العمل لإدارة الحسابات والخدمات ومتابعة العمليات.</p></div>
<div class="ops-grid">
<article class="card ops-card"><h2>الحسابات وفريق العمل</h2><p class="ops-help">تنظيم حسابات المستخدمين والأقسام وتفويض الموظفين.</p><div class="ops-toolbar"><a class="btn btn-primary" href="{{ route('admin.users.index') }}">المستخدمون</a><a class="btn btn-outline" href="{{ route('admin.employees.index') }}">الموظفون</a><a class="btn btn-outline" href="{{ route('admin.departments.index') }}">الأقسام</a><a href="{{ route('admin.roles.index') }}">الأدوار</a></div></article>
<article class="card ops-card"><h2>الخدمات والصلاحيات</h2><p class="ops-help">إعداد الخدمات وقواعدها وتحديد صلاحيات المراجعة والإصدار.</p><div class="ops-toolbar"><a class="btn btn-primary" href="{{ route('admin.services.index') }}">إدارة الخدمات</a><a class="btn btn-outline" href="{{ route('admin.review-permissions.index') }}">صلاحيات الخدمات</a></div></article>
<article class="card ops-card"><h2>التقارير والرقابة</h2><p class="ops-help">استعراض المؤشرات الفعلية ومراجعة أثر العمليات الإدارية.</p><div class="ops-toolbar"><a class="btn btn-primary" href="{{ route('admin.reports.index') }}">التقارير والإحصائيات</a><a class="btn btn-outline" href="{{ route('admin.audit-logs.index') }}">سجل التدقيق</a></div></article>
<article class="card ops-card"><h2>إعدادات المنصة</h2><p class="ops-help">حالة التكاملات والتحقق الإلكتروني ومعلومات تكوين المنصة.</p><div class="ops-toolbar"><a class="btn btn-primary" href="{{ route('admin.integrations.index') }}">التكاملات</a><a class="btn btn-outline" href="{{ route('admin.settings.index') }}">الإعدادات</a></div></article>
</div><form method="POST" action="{{ route('logout') }}" class="ops-toolbar">@csrf<button type="submit" class="btn btn-outline">تسجيل الخروج</button></form>
</div></section>
@endsection