@extends('layouts.public')
@section('title','حسابي وطلباتي')
@section('content')
<section class="ops-section"><div class="container">
<x-workspace-heading title="مرحبًا، {{ explode(' ', trim(auth()->user()->name))[0] }}" role-label="مساحة المواطن" description="تابع معاملاتك واستكمل المطلوب منك من مكان واحد." />
@include('operations.messages')
<article class="card ops-card workspace-notice"><h2>حالة حسابك</h2><p>{{ auth()->user()->accountStatusLabel() }}</p>@if(!auth()->user()->hasActivatedIdentity())<p>اختر «تفعيل حسابي» من قائمة الحساب لاستكمال الإجراء المناسب لحالة هويتك.</p>@endif</article>
<div class="ops-grid" aria-label="ملخص حسابي">@foreach(['إجمالي الطلبات'=>auth()->user()->serviceRequests()->count(),'تحتاج استكمالًا'=>auth()->user()->serviceRequests()->where('status','requires_correction')->count(),'وثائق مسجلة'=>auth()->user()->documents()->count(),'مواعيد مفتوحة'=>auth()->user()->appointments()->whereIn('status',['booked','confirmed'])->count()] as $label=>$value)<article class="ops-metric"><strong>{{ $value }}</strong><p>{{ $label }}</p></article>@endforeach</div>
<article class="card ops-card"><h2>أحدث معاملاتي</h2><p class="ops-help">ابدأ بالطلبات التي تحتاج استكمال بيانات أو مستندات.</p><div class="ops-table-wrap"><table><thead><tr><th>المرجع الداخلي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@forelse(auth()->user()->serviceRequests()->orderByRaw("CASE WHEN status = 'requires_correction' THEN 0 ELSE 1 END")->latest('id')->limit(5)->get() as $application)<tr><td>{{ $application->reference }}</td><td><span class="ops-status">{{ $application->statusLabel() }}</span></td><td><a href="{{ route('citizen.applications.show',$application->id) }}">{{ $application->status==='requires_correction'?'استكمال المطلوب':'تفاصيل الطلب' }}</a></td></tr>@empty<tr><td colspan="3" class="ops-empty">لم تقدم طلبات بعد. اختر خدمة من قائمة الحساب وراجع متطلباتها قبل البدء.</td></tr>@endforelse</tbody></table></div></article>
<article class="card ops-card"><h2>خطوات إنجاز معاملتك</h2><ol class="workspace-steps"><li>راجع بيانات حسابك ومتطلبات الخدمة.</li><li>عبئ الطلب وأرفق المستندات ثم أرسله.</li><li>تابع الإشعارات لاستكمال أي نواقص أو موعد مطلوب.</li></ol><p class="ops-help">الوثائق الداخلية التجريبية لا تعد وثائق حكومية نافذة.</p></article>
</div></section>
@endsection
