@extends('layouts.public')
@section('title','حسابي وطلباتي')
@section('content')
<x-workspace-dashboard dashboard-role="citizen">
<x-workspace-heading title="مرحبًا، {{ explode(' ', trim(auth()->user()->name))[0] }}" role-label="مساحة المواطن" description="تابع معاملاتك واستكمل المطلوب منك من مكان واحد." />
@include('operations.messages')
<article class="card ops-card workspace-notice"><h2>حالة حسابك</h2><p>{{ auth()->user()->accountStatusLabel() }}</p>@if(!auth()->user()->hasActivatedIdentity())<p>اختر «تفعيل حسابي» من قائمة الحساب لاستكمال الإجراء المناسب لحالة هويتك.</p>@endif</article>
<x-workspace-quick-links title="خدمات حسابي" :items="[
    ['label' => 'طلباتي', 'description' => 'متابعة الحالات واستكمال النواقص.', 'route' => 'citizen.applications.index'],
    ['label' => 'بياناتي', 'description' => 'مراجعة بيانات الحساب المسجلة.', 'route' => 'citizen.data'],
    ['label' => 'مواعيدي', 'description' => 'عرض المواعيد والحجوزات الخاصة بك.', 'route' => 'citizen.appointments.index'],
    ['label' => 'وثائقي', 'description' => 'استعراض الوثائق المسجلة لحسابك.', 'route' => 'citizen.documents.index'],
    ['label' => 'الإشعارات', 'description' => 'متابعة التحديثات المرتبطة بطلباتك.', 'route' => 'citizen.notifications.index'],
    ['label' => 'تفعيل الحساب', 'description' => 'عرض حالة التفعيل والإجراء المناسب.', 'route' => 'citizen.account.status'],
]" />
<div class="ops-grid" aria-label="ملخص حسابي">@foreach($metrics as $label=>$value)<article class="ops-metric"><strong>{{ $value }}</strong><p>{{ $label }}</p></article>@endforeach</div>
<article class="card ops-card"><h2>أحدث معاملاتي</h2><p class="ops-help">ابدأ بالطلبات التي تحتاج استكمال بيانات أو مستندات.</p><div class="ops-table-wrap"><table><thead><tr><th>المرجع الداخلي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@forelse($recentApplications as $application)<tr><td>{{ $application->reference }}</td><td><span class="ops-status">{{ $application->statusLabel() }}</span></td><td><a href="{{ route('citizen.applications.show',$application->id) }}">{{ $application->status==='requires_correction'?'استكمال المطلوب':'تفاصيل الطلب' }}</a></td></tr>@empty<tr><td colspan="3" class="ops-empty">لم تقدم طلبات بعد. اختر خدمة من قائمة الحساب وراجع متطلباتها قبل البدء.</td></tr>@endforelse</tbody></table></div></article>
<article class="card ops-card"><h2>خطوات إنجاز معاملتك</h2><ol class="workspace-steps"><li>راجع بيانات حسابك ومتطلبات الخدمة.</li><li>عبئ الطلب وأرفق المستندات ثم أرسله.</li><li>تابع الإشعارات لاستكمال أي نواقص أو موعد مطلوب.</li></ol><p class="ops-help">الوثائق الداخلية التجريبية لا تعد وثائق حكومية نافذة.</p></article>
</x-workspace-dashboard>
@endsection
