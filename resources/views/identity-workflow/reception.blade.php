@extends('layouts.public')
@section('title', 'استقبال طلبات البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-workflow.css') }}">@endpush
@section('content')
<section class="section"><div class="container identity-workflow">
<h1>استقبال طلبات البطاقة الشخصية</h1><p class="workflow-warning">بيئة تجريبية داخل النظام. تسجيل الحضور وفحص المستندات إجراء يدوي موثق، ولا يمثل إصدار بطاقة رسمية.</p>
@if(session('status'))<p class="workflow-status" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="workflow-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="GET" action="{{ route('identity-workflow.reception') }}" class="workflow-panel"><label for="reference">مرجع الطلب أو الاستمارة</label><div class="workflow-actions"><input id="reference" name="reference" value="{{ request('reference') }}"><button class="btn btn-primary">بحث</button></div></form>
@if($form)
<section class="workflow-panel"><h2>{{ data_get($form->data, 'applicant_name') }}</h2><p dir="ltr">{{ $form->application?->reference ?? $form->reference }}</p><p>{{ $form->statusLabel() }}</p>
@if(count($reasons))<ul class="workflow-warning">@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
@if($form->arrived_at)<p>الحضور مسجل: {{ $form->arrived_at->format('Y-m-d H:i') }} · المرجع: {{ $form->arrival_reference }}</p>@endif
@if(!$form->arrived_at && in_array($form->status, ['pending_fingerprint_attendance','fingerprint_failed'], true))
<form method="POST" action="{{ route('identity-workflow.arrival', $form) }}">@csrf<input type="hidden" name="version" value="{{ $form->version }}">
<div class="workflow-field"><label><input type="checkbox" name="identity_checked" value="1" required @checked(old('identity_checked'))> تحققت من هوية مقدم الطلب حضوريًا</label>@error('identity_checked')<p class="workflow-error">{{ $message }}</p>@enderror</div>
<div class="workflow-field"><label><input type="checkbox" name="documents_checked" value="1" required @checked(old('documents_checked'))> فحصت المستندات الورقية المطلوبة</label>@error('documents_checked')<p class="workflow-error">{{ $message }}</p>@enderror</div>
<div class="workflow-field"><label for="arrival-evidence">مرجع محضر الحضور وفحص المستندات</label><input id="arrival-evidence" name="evidence_reference" required value="{{ old('evidence_reference') }}">@error('evidence_reference')<p class="workflow-error">{{ $message }}</p>@enderror</div><button class="btn btn-primary" @disabled(count($reasons))>تسجيل الحضور والفحص</button></form>
@endif
</section>
@if($form->attestation?->status === 'approved' && $form->attestation?->authority_type === 'neighborhood' && $form->attestation?->security_required && $form->attestation?->security_status !== 'recorded_manually')
<section class="workflow-panel"><h2>تسجيل مراجعة الورقة الأمنية</h2><p>سجل مراجعة الورقة الفعلية فقط. هذا التسجيل ليس ختمًا أمنيًا أو مصادقة آلية.</p><form method="POST" action="{{ route('identity-workflow.security', $form) }}">@csrf<input type="hidden" name="version" value="{{ $form->version }}"><div class="workflow-field"><label><input type="checkbox" name="paper_checked" value="1" required> راجعت الورقة الأمنية يدويًا</label>@error('paper_checked')<p class="workflow-error">{{ $message }}</p>@enderror</div><div class="workflow-field"><label for="security-evidence">مرجع الورقة الأمنية</label><input id="security-evidence" name="evidence_reference" required value="{{ old('evidence_reference') }}">@error('evidence_reference')<p class="workflow-error">{{ $message }}</p>@enderror</div><button class="btn btn-outline">تسجيل مراجعة الورقة</button></form></section>
@endif
@if(count($fingerprintReasons))<ul class="workflow-warning">@foreach($fingerprintReasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
@if($form->status === 'returned_to_staff')<form method="POST" action="{{ route('identity-workflow.administration.requeue', $form) }}">@csrf<input type="hidden" name="version" value="{{ $form->version }}"><label for="staff-requeue-note">ملاحظة الاستكمال الداخلية</label><textarea id="staff-requeue-note" name="note" required></textarea><button class="btn btn-outline">إعادة إلى المراجعة الإدارية</button></form>@endif
@if($canRecord)<a class="btn btn-primary" href="{{ route('identity-workflow.device', $form) }}">فتح محطة البصمة التجريبية</a>@endif
@can('administer', $form)<a class="btn btn-outline" href="{{ route('identity-workflow.administration.show', $form) }}">المراجعة الإدارية</a>@endcan
@endif
@if(request('reference') && !$form)<p role="status">لا يوجد طلب بهذا المرجع ضمن صلاحياتك.</p>@endif
<section class="workflow-panel"><h2>الطلبات المتاحة</h2><div class="workflow-table-wrap"><table class="workflow-table"><thead><tr><th>المرجع</th><th>مقدم الطلب</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@forelse($forms as $item)<tr><td>{{ $item->application?->reference ?? $item->reference }}</td><td>{{ data_get($item->data,'applicant_name') }}</td><td>{{ $item->statusLabel() }}</td><td><a href="{{ route('identity-workflow.reception', ['reference'=>$item->reference]) }}">فتح</a></td></tr>@empty<tr><td colspan="4">لا توجد طلبات متاحة.</td></tr>@endforelse</tbody></table></div>{{ $forms->links() }}</section>
</div></section>
@endsection
