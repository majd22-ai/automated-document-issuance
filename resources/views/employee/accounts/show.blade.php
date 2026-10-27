@extends('layouts.public')
@section('title', 'اعتماد تفعيل حساب المواطن')
@section('content')
@if($citizen->activationRequest)
<div class="container alert alert-info">طلب تفعيل مرسل من المواطن برقم {{ $citizen->activationRequest->id }} بتاريخ {{ $citizen->activationRequest->submitted_at }} — {{ match($citizen->activationRequest->status) { 'approved'=>'تم الاعتماد', 'review_required'=>'يحتاج مراجعة آمنة', default=>'بانتظار المراجعة' } }}</div>
@endif
<section class="section" dir="rtl"><div class="container"><div class="ops-page-head"><h1>اعتماد تفعيل حساب المواطن</h1><p class="ops-help">راجع بيانات الحساب وسجل الإجراءات، ثم استكمل الإقرار والمرجع الرسمي في نموذج الاعتماد.</p></div>@include('operations.messages')
<div class="card ops-card" style="padding:24px"><h2>{{ $citizen->name }}</h2><p>معرّف الحساب: {{ $citizen->id }} — اسم المستخدم: {{ $citizen->username }}</p><p>{{ $citizen->accountStatusLabel() }}</p><p>التفعيل المسجل: {{ $citizen->account_activated_at ? 'نعم' : 'لا' }} — توثيق الهوية العام: {{ $citizen->identity_verified_at ? 'نعم' : 'لا' }}</p></div>
@if(!$citizen->hasActivatedIdentity())
<form method="POST" action="{{ route('employee.accounts.confirm', $citizen) }}" class="card ops-card" style="padding:24px;margin-block:20px">
@csrf<input type="hidden" name="operation_token" value="{{ $operationToken }}">
@php($initialOrigin = $citizen->identity_path === 'no_identity' || $citizen->registration_status === 'initial_account')
<input type="hidden" name="action" value="{{ $initialOrigin ? 'confirm_issuance' : 'activate' }}">
<h2>{{ $initialOrigin ? 'تأكيد الإصدار الرسمي وترقية الحساب نفسه' : 'تفعيل الحساب والتحقق من الهوية' }}</h2>
<p>لا تعتمد الحضور أو الصورة أو البصمة وحدها. راجع المرجع الرسمي والمستندات المعتمدة أولًا. هذه مسؤولية الموظف المخوّل؛ لا يوجد تكامل إصدار حكومي آلي مربوط حاليًا.</p>
<label for="activation-national-id" style="display:block;margin-block:1rem .4rem">الرقم الوطني الرسمي</label><input class="form-control" id="activation-national-id" name="national_id" value="{{ old('national_id', $citizen->nationalIdentity()) }}" pattern="[0-9]{5,20}" maxlength="20" required @if(!$initialOrigin) readonly @endif><small>لا تستخدم رقم الهاتف أو معرّف الحساب أو الرقم الداخلي المولّد للوثيقة بدل الرقم الوطني الرسمي.</small>
@if($initialOrigin)
<label for="activation-request-id" style="display:block;margin-block:1rem .4rem">طلب البطاقة المكتمل ضمن مسار الإصدار والتسليم الحالي</label><select class="form-control" id="activation-request-id" name="request_id" required><option value="">اختر الطلب المرتبط بالمواطن</option>@foreach($requests as $application)<option value="{{ $application->id }}" @selected((string)old('request_id') === (string)$application->id)>{{ $application->reference }} — {{ $application->statusLabel() }}</option>@endforeach</select>{{ $requests->links() }}
<input type="hidden" name="official_issuance_confirmed" value="0"><label><input type="checkbox" name="official_issuance_confirmed" value="1" required> أؤكد من مصدر رسمي اكتمال إجراءات الإصدار وصدور الرقم الوطني والبطاقة بنجاح.</label>
@else<input type="hidden" name="official_issuance_confirmed" value="0">@endif
<label for="activation-reference" style="display:block;margin-block:1rem .4rem">مرجع التحقق الرسمي (محفوظ مشفرًا)</label><input class="form-control" id="activation-reference" name="evidence_reference" minlength="8" maxlength="255" required autocomplete="off"><small>مرجع فقط؛ لا ترفع صورًا أو بصمات أو تضع أسرارًا في هذا الحقل.</small>
<input type="hidden" name="identity_confirmed" value="0"><label><input type="checkbox" name="identity_confirmed" value="1" @if($initialOrigin) required @endif> اكتمل التحقق الرسمي من هوية المواطن وارتباطها بالحساب.</label>
@if(!$initialOrigin)<p>إذا لم تكتمل هوية المواطن، يسجل التفعيل الإداري وحده وتبقى الخدمات التي تتطلب هوية موثقة محجوبة.</p>@endif
<div style="margin-block-start:1rem"><button class="btn btn-primary" type="submit">اعتماد الإجراء على الحساب الحالي</button></div></form>
@else<p class="alert alert-success">التفعيل وتوثيق الهوية مكتملان على الحساب الحالي. لا يلزم إجراء تفعيل جديد.</p>@endif
<h2>سجل إجراءات الحساب</h2><div class="card ops-card" style="overflow-x:auto" tabindex="0" role="region" aria-label="جدول السجلات"><table class="table" style="width:100%;text-align:start"><thead><tr><th scope="col">الوقت</th><th scope="col">الموظف</th><th scope="col">الإجراء</th><th scope="col">النتيجة</th><th scope="col">توثيق الهوية في الإقرار</th></tr></thead><tbody>@forelse($confirmations as $confirmation)<tr><td>{{ $confirmation->confirmed_at }}</td><td>{{ $confirmation->actor?->name }}</td><td>{{ $confirmation->action === 'confirm_issuance' ? 'تأكيد إصدار رسمي' : 'اعتماد حساب' }}</td><td>{{ $confirmation->result === 'confirmed' ? 'تم الاعتماد' : 'تعارض — لم تعتمد الهوية' }}</td><td>{{ $confirmation->identity_confirmed ? 'مؤكد في الإقرار' : 'غير مكتمل' }}</td></tr>@empty<tr><td colspan="5">لا توجد إجراءات اعتماد مسجلة.</td></tr>@endforelse</tbody></table></div>{{ $confirmations->links() }}
</div></section>
@endsection
