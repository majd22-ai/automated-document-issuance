@extends('layouts.public')
@section('title', 'لوحة عمل الموظف - منصة الإصدار الآلي')
@section('content')
@php
    $staffGrants = auth()->user()->reviewPermissions()->get();
    $hasStaffGrant = fn ($ability) => $staffGrants->contains(fn ($permission) => (bool) $permission->{$ability});
@endphp
<section class="section"><div class="container">
    <div class="ops-page-head"><h1>لوحة عمل الموظف</h1><p class="ops-help">ابدأ بقائمة المراجعة، ثم تابع التحقق والإصدار والتسليم حسب الصلاحية الممنوحة لخدماتك.</p></div>
    @include('operations.messages')
    <div class="ops-toolbar" style="margin-block:1rem"><a class="btn btn-outline" href="{{ route('employee.notifications.index') }}">إشعاراتي <span class="ops-status">{{ auth()->user()->unreadNotifications()->count() }} غير مقروءة</span></a></div>
    <div class="ops-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:1rem">
        @if($hasStaffGrant('can_review') || $hasStaffGrant('can_approve') || $hasStaffGrant('can_assign'))
<article class="card ops-card" style="padding:1.5rem"><h2>مراجعة الطلبات</h2><p>ابحث عن الطلبات ضمن خدماتك، وراجع المتطلبات والمرفقات قبل القرار.</p><a class="btn btn-primary" href="{{ route('employee.applications.index') }}">فتح قائمة المراجعة</a><p><a href="{{ route('employee.applications.index', ['mine'=>1]) }}">الطلبات المسندة إليّ</a></p></article>
@endif
        <article class="card ops-card" style="padding:1.5rem"><h2>المرفقات</h2><p>راجع صور ومستندات المواطنين وسجل القبول أو طلب الاستبدال مع السبب.</p><a class="btn btn-outline" href="{{ route('employee.attachments.index') }}">مراجعة المرفقات</a></article>
        <article class="card ops-card" style="padding:1.5rem"><h2>ملفات المواطنين</h2><p>ابحث عن المواطن واستعرض بياناته ووثائقه وطلبات الخدمات المرتبطة.</p><a class="btn btn-outline" href="{{ route('employee.citizens.index') }}">البحث عن مواطن</a></article>
        @can('manageCitizenActivation', \App\Models\User::class)
        <article class="card ops-card" style="padding:1.5rem"><h2>تفعيل الحسابات</h2><p><span class="ops-status">{{ \App\Models\CitizenActivationRequest::where('status','pending')->count() }} طلب بانتظار المراجعة</span></p><p>راجع طلب التفعيل والمرجع الرسمي قبل اعتماد الحساب.</p><a class="btn btn-outline" href="{{ route('employee.accounts.index', ['submitted'=>1]) }}">طلبات التفعيل المرسلة</a></article>
        @endcan
        <article class="card ops-card" style="padding:1.5rem"><h2>المواعيد</h2><p>تابع حجوزات المواطنين ومراكز الخدمة وحالات الحضور.</p><a class="btn btn-outline" href="{{ route('employee.appointments.index') }}">إدارة المواعيد</a></article>
        <article class="card ops-card" style="padding:1.5rem"><h2>الرسوم والمدفوعات</h2><p>راجع الالتزامات المالية والإيصالات المسجلة يدويًا.</p><a class="btn btn-outline" href="{{ route('employee.payments.index') }}">متابعة الرسوم</a></article>
        @if($hasStaffGrant('can_verify'))
<article class="card ops-card" style="padding:1.5rem"><h2>التحقق الإلكتروني</h2><p>راجع حالة مصادر التحقق ومحاولات الاستعلام ضمن خدماتك المخولة.</p><a class="btn btn-outline" href="{{ route('employee.verifications.index') }}">طلبات التحقق</a></article>
@endif
        @if($hasStaffGrant('can_issue'))
<article class="card ops-card" style="padding:1.5rem"><h2>إصدار الوثائق</h2><p>راجع جاهزية الطلبات المعتمدة قبل إصدار السجل الداخلي للوثيقة.</p><a class="btn btn-outline" href="{{ route('employee.issuance.index') }}">طلبات الإصدار</a></article>
@endif
        @if($hasStaffGrant('can_deliver'))
<article class="card ops-card" style="padding:1.5rem"><h2>التسليم والاستلام</h2><p>تابع طريقة الاستلام وسجل حالة التسليم ومرجع الإثبات.</p><a class="btn btn-outline" href="{{ route('employee.deliveries.index') }}">متابعة التسليم</a></article>
@endif
    </div>
</div></section>
<section class="section"><div class="container"><details><summary>فتح المعاينة التجريبية السابقة</summary><p class="ops-help">المعاينة السابقة محفوظة للتجربة فقط، وبياناتها مستقلة عن الطلبات الفعلية. استخدم أقسام العمل أعلاه لتنفيذ الإجراءات على سجلات المنصة.</p>@include('components.portal-demo', ['screen' => 'review'])</details></div></section>
@endsection
