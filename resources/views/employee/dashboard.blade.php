@extends('layouts.public')
@section('title','مساحة عمل الموظف')
@section('content')
<x-workspace-dashboard dashboard-role="employee">
<x-workspace-heading title="مرحبًا، {{ explode(' ', trim(auth()->user()->name))[0] }}" role-label="مساحة الموظف" description="تعرض هذه المساحة المهام والخدمات التي فوضك بها مدير النظام فقط." />
@include('operations.messages')
@if(count($modules) > 1)
<x-workspace-quick-links title="أقسام عملي المتاحة" :items="array_values(array_filter($modules, fn ($module) => $module['key'] !== 'dashboard'))" />
@endif
@if(count($modules)===1)<div class="ops-empty"><h2>لا توجد مهام مفوضة حاليًا</h2><p>يحدد مدير النظام صلاحياتك قبل بدء العمل. يبقى ملفك الشخصي وإشعاراتك متاحين من الشريط العلوي.</p></div>@endif
@if(\App\Models\NeighborhoodOfficial::authorized(auth()->user()))
<article class="card ops-card workspace-notice"><h2>اختصاصك: اعتماد السكن في حيك</h2><p>تظهر طلبات الشهادات الموجهة إليك فقط في قسم «شهادات السكن». لا يمنح هذا الاختصاص الوصول إلى معاملات الموظفين الآخرين أو ملفاتهم.</p></article>
@endif
<div class="ops-grid" aria-label="ملخص العمل">
<article class="ops-metric"><strong>{{ max(0,count($modules)-1) }}</strong><p>أقسام عمل متاحة حسب التفويض</p></article>
<article class="ops-metric"><strong>{{ $grants->filter(fn($grant)=>collect(['can_review','can_approve','can_assign','can_issue','can_deliver','can_verify','can_activate'])->contains(fn($ability)=>(bool)$grant->{$ability}))->count() }}</strong><p>خدمات مفوضة</p></article>
</div>
@if($grants->isNotEmpty())
<article class="card ops-card"><h2>خدماتي وصلاحياتي</h2><p class="ops-help">هذه الصلاحيات للعرض فقط؛ تعديلها متاح لمدير النظام.</p><div class="ops-table-wrap"><table><thead><tr><th>الخدمة</th><th>المهام المسموحة</th></tr></thead><tbody>
@foreach($grants as $grant)@php($abilities=collect(['can_review'=>'المراجعة','can_approve'=>'الاعتماد','can_assign'=>'الإسناد','can_issue'=>'الإصدار','can_deliver'=>'التسليم','can_verify'=>'التحقق','can_activate'=>'تفعيل الحسابات'])->filter(fn($label,$key)=>(bool)$grant->{$key}))
@if($abilities->isNotEmpty())<tr><td>{{ $grant->service?->name }}</td><td>{{ $abilities->implode('، ') }}</td></tr>@endif
@endforeach
</tbody></table></div></article>@endif
@if($assigned->isNotEmpty())<article class="card ops-card"><h2>آخر الطلبات المسندة إليّ</h2><div class="ops-table-wrap"><table><thead><tr><th>المرجع الداخلي</th><th>الخدمة</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@foreach($assigned as $application)<tr><td>{{ $application->reference }}</td><td>{{ $application->service?->name }}</td><td>{{ $application->statusLabel() }}</td><td>@can('viewReview',$application)<a href="{{ route('employee.applications.review',$application->id) }}">مراجعة هذا الطلب</a>@else @can('track',$application)<a href="{{ route('employee.tracking.show',$application->id) }}">عرض المعاملة</a>@endcan @endcan</td></tr>@endforeach</tbody></table></div></article>@endif
<article class="card ops-card"><h2>كيف أبدأ؟</h2><ol class="workspace-steps"><li>اختر قسم عملك من قائمة مساحة العمل.</li><li>افتح الطلب المطلوب وراجع بياناته والمتطلبات المسموحة لك.</li><li>سجل الإجراء وسببه، ثم تأكد من رسالة نجاح الحفظ.</li></ol><p class="ops-help">تتجدد صلاحيات الوصول عند كل طلب. إذا تغير تفويضك فلن تتمكن من تنفيذ الإجراء القديم.</p></article>
</div>
</x-workspace-dashboard>
@endsection
