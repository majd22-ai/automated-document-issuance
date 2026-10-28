@extends('layouts.public')
@section('title', 'المراجعة الإدارية للاستمارة')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-workflow.css') }}"><link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}">@endpush
@section('content')
<section class="section"><div class="container identity-workflow"><h1>المراجعة الإدارية للاستمارة</h1><p class="workflow-warning">مسار تجريبي داخل المنصة؛ لا يمثل إصدار بطاقة رسمية.</p><p>{{ $form->reference }} · {{ $form->statusLabel() }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="workflow-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(count($reasons))<section class="workflow-panel"><h2>متطلبات لم تكتمل</h2><ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></section>@endif
@include('components.identity-card-summary', ['identityForm'=>$form])
@if($form->attestation)<section class="workflow-panel"><h2>المصادقة الحالية</h2><p>{{ $form->attestation->authorityLabel() }} · {{ $form->attestation->statusLabel() }}</p><p>{{ $form->attestation->reference }}</p>@if($form->attestation->rejection_reason)<p>سبب الرفض: {{ $form->attestation->rejection_reason }}</p>@endif</section>@endif
<section class="workflow-panel"><h2>سجل البصمة</h2><p>{{ $form->fingerprintStatusLabel() }}</p>@forelse($form->operations as $operation)<article class="workflow-history"><p>{{ $operation->reference }} · {{ $operation->sourceLabel() }} · {{ $operation->resultLabel() }}</p><p>{{ $operation->executed_at?->format('Y-m-d H:i') }} · {{ $operation->operator?->name }}</p><p>{{ $operation->error_detail ?: $operation->evidence_reference }}</p></article>@empty<p>لا توجد عمليات مسجلة.</p>@endforelse</section>
@can('administer', $form)
@if(in_array($form->status, ['pending_administrative_review','returned_to_staff'], true))
<section class="workflow-panel"><h2>تسجيل قرار إداري</h2><form method="POST" action="{{ route('identity-workflow.administration.decide', $form) }}">@csrf<input type="hidden" name="version" value="{{ $form->version }}"><input type="hidden" name="decision_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div class="workflow-field"><label for="internal-note">ملاحظة داخلية للموظفين (اختيارية)</label><textarea name="internal_note" id="internal-note">{{ old('internal_note') }}</textarea>@error('internal_note')<p class="workflow-error">{{ $message }}</p>@enderror</div>
<div class="workflow-field"><label for="citizen-reason">سبب يظهر للمواطن (مطلوب للتصحيح أو الرفض)</label><textarea name="citizen_reason" id="citizen-reason">{{ old('citizen_reason') }}</textarea>@error('citizen_reason')<p class="workflow-error">{{ $message }}</p>@enderror</div>
@error('action')<p class="workflow-error">{{ $message }}</p>@enderror
<div class="workflow-actions"><button class="btn btn-primary" name="action" value="approved" @disabled(count($reasons) || $form->status !== 'pending_administrative_review')>موافقة إدارية تجريبية</button><button class="btn btn-outline" name="action" value="returned_to_staff">إعادة إلى الموظف</button><button class="btn btn-outline" name="action" value="correction_required">طلب تصحيح من المواطن</button><button class="btn btn-outline" name="action" value="rejected">رفض الطلب</button></div></form></section>
@endif
@endcan
@can('recordFingerprint', $form)
@if($form->status === 'returned_to_staff')
<section class="workflow-panel"><h2>إعادة الطلب إلى المراجعة</h2><form method="POST" action="{{ route('identity-workflow.administration.requeue', $form) }}">@csrf<input type="hidden" name="version" value="{{ $form->version }}"><div class="workflow-field"><label for="requeue-note">ما تم استكماله (مطلوب)</label><textarea name="note" id="requeue-note" required>{{ old('note') }}</textarea>@error('note')<p class="workflow-error">{{ $message }}</p>@enderror</div><button class="btn btn-outline">إعادة إلى قائمة المراجعة</button></form></section>
@endif
@endcan
<section class="workflow-panel"><h2>سجل القرارات الإدارية</h2>@forelse($decisions as $decision)<article class="workflow-history"><p>{{ \App\Models\IdentityAdministrativeDecision::ACTIONS[$decision->action] ?? $decision->action }} · {{ $decision->decided_at?->format('Y-m-d H:i') }}</p><p>سبب للمواطن: {{ $decision->citizen_reason ?: 'غير مسجل' }}</p><p>ملاحظة داخلية: {{ $decision->internal_note ?: 'غير مسجلة' }}</p></article>@empty<p>لا توجد قرارات سابقة.</p>@endforelse</section>
<a class="btn btn-outline" href="{{ route('identity-workflow.print', $form) }}">معاينة نموذج الطباعة</a>
</div></section>
@endsection

