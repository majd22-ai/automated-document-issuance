@extends('layouts.public')
@section('title', 'إعدادات النظام')
@section('content')
<section class="ops-section"><div class="container">
<div class="ops-page-head"><h1>إعدادات النظام</h1><p>معلومات المنصة وروابط إدارة التكوين المتاح.</p></div>
<div class="ops-grid">
<article class="card ops-card"><h2>معلومات المنصة</h2><dl><dt>اسم التطبيق</dt><dd>{{ config('app.name') }}</dd><dt>المنطقة الزمنية</dt><dd dir="ltr">{{ config('app.timezone') }}</dd></dl><p class="ops-help">هذه المعلومات للعرض. لا تتوفر حاليًا خاصية تعديل الإعدادات العامة من هذه الصفحة.</p></article>
<article class="card ops-card"><h2>التكامل والتحقق</h2><p>استعرض حالة مزودي التحقق وسجل المحاولات ومتطلبات التحقق للخدمات.</p><a class="btn btn-primary" href="{{ route('admin.integrations.index') }}">فتح إعدادات التكامل</a></article>
<article class="card ops-card"><h2>التحكم في الوصول</h2><p>إدارة الأدوار وحالة الحسابات والصلاحيات الممنوحة لكل خدمة.</p><div class="ops-toolbar"><a class="btn btn-outline" href="{{ route('admin.users.index') }}">الحسابات والأدوار</a><a class="btn btn-outline" href="{{ route('admin.review-permissions.index') }}">صلاحيات الخدمات</a></div></article>
<article class="card ops-card"><h2>المراجعة والتدقيق</h2><p>راجع العمليات الإدارية والتشغيلية المسجلة في المنصة.</p><a class="btn btn-outline" href="{{ route('admin.audit-logs.index') }}">فتح سجل التدقيق</a></article>
</div></div></section>
@endsection