@extends('layouts.public')
@section('title', 'لوحة تحكم المواطن - منصة الإصدار الآلي')
@section('content')
    <section class="section"><div class="container ops-content">
        <x-workspace-heading title="لوحة تحكم المواطن" role-label="مساحة المواطن" description="تابع طلباتك ووثائقك ومواعيدك، واستكمل الإجراءات المطلوبة منك." />
        <div class="ops-grid" aria-label="ملخص حسابي">
        @foreach(['طلباتي' => auth()->user()->serviceRequests()->count(), 'طلبات تحتاج استكمالًا' => auth()->user()->serviceRequests()->where('status', 'requires_correction')->count(), 'وثائقي المسجلة' => auth()->user()->documents()->count(), 'مواعيدي المفتوحة' => auth()->user()->appointments()->whereIn('status', ['booked', 'confirmed'])->count()] as $label => $value)
            <article class="ops-metric"><strong>{{ $value }}</strong><p>{{ $label }}</p></article>
        @endforeach
        </div>
        <article class="card ops-card"><h2>أحدث طلباتي</h2><div class="ops-table-wrap"><table><thead><tr><th scope="col">الرقم المرجعي الداخلي</th><th scope="col">الحالة</th><th scope="col">الإجراء</th></tr></thead><tbody>
        @forelse(auth()->user()->serviceRequests()->latest('id')->limit(5)->get() as $application)
            <tr><td>{{ $application->reference }}</td><td>{{ $application->statusLabel() }}</td><td><a href="{{ route('citizen.applications.show', $application->id) }}">عرض الطلب واستكمال المتطلبات</a></td></tr>
        @empty
            <tr><td colspan="3">لم تقدّم طلبات بعد. استعرض الخدمات ومتطلباتها قبل البدء.</td></tr>
        @endforelse
        </tbody></table></div><a href="{{ route('citizen.applications.index') }}">جميع طلباتي</a></article>
        <div class="ops-grid">
        <article class="card ops-card"><h2>إصدار البطاقة الشخصية</h2><p>استمارة متعددة المراحل مع حفظ المسودة والصورة وبيانات الشاهدين.</p><a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">فتح خدمة إصدار البطاقة الشخصية</a></article>
        <article class="card ops-card"><h2>المواعيد والرسوم</h2><p>تابع المواعيد والرسوم المرتبطة بطلباتك.</p><div class="ops-toolbar"><a class="btn btn-outline" href="{{ route('citizen.appointments.index') }}">مواعيدي</a><a class="btn btn-outline" href="{{ route('citizen.payments.index') }}">رسومي ومدفوعاتي</a></div></article>
        </div>
    </div></section>
    <section class="section"><div class="container"><p>{{ auth()->user()->accountStatusLabel() }} — <a href="{{ route('citizen.account.status') }}">حالة الحساب وتعليمات الحضور والتفعيل</a></p></div></section>
    <section class="section"><div class="container"><a class="btn btn-primary" href="{{ route('citizen.data') }}">بياناتي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.documents.index') }}">وثائقي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">طلباتي المسجلة</a><p>السجلات الفعلية متاحة عبر الروابط أعلاه. الواجهة التالية معاينة تجريبية مستقلة وليست بيانات حكومية.</p></div></section>
    <section class="section"><div class="container"><details><summary>فتح معاينة تجريبية مستقلة — ليست بيانات حسابك</summary>
    @include('components.portal-demo', ['screen' => 'dashboard'])
    </details></div></section>
@endsection
