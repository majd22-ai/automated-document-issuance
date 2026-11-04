@extends('layouts.public')
@section('title', 'مراجعة شهادة السكن')
@section('content')
<section class="ops-section"><h1>مراجعة شهادة سكن المواطن</h1>
<p class="alert" role="note">محاكاة محلية واضحة: التأكيد أدناه لا يقرأ بصمة الهاتف أو رمز قفله، ولا يمثل ختمًا حكوميًا.</p>
@include('operations.messages')
<article class="card ops-card"><h2>{{ $attestation->citizen_name }}</h2><p>رقم الطلب الداخلي: {{ $attestation->form->application->reference }}</p>
<dl>@foreach(['governorate'=>'المحافظة','district'=>'المديرية','neighborhood'=>'الحي'] as $field=>$label)<dt>{{ $label }}</dt><dd>{{ data_get($attestation->recipient_scope,$field) }}</dd>@endforeach</dl>
<p>عنوان السكن: {{ data_get($attestation->form->data,'street') }} — {{ data_get($attestation->form->data,'house') }}</p>
<p>الحالة: {{ $attestation->statusLabel() }}</p>@if($attestation->rejection_reason)<p>{{ $attestation->rejection_reason }}</p>@endif</article>
@include('components.identity-attestation-stamp')
@if($attestation->status === 'pending')
<button type="button" class="btn btn-primary" onclick="document.getElementById('attestation-confirm').showModal()">أوافق على اعتماد شهادة السكن</button>
<dialog id="attestation-confirm" aria-labelledby="confirmation-title" style="max-width:min(92vw,480px);padding:24px;border-radius:16px">
<form method="dialog"><button aria-label="إغلاق" class="btn btn-outline">×</button></form><h2 id="confirmation-title">تأكيد تجريبي للموافقة</h2>
<p>اختر محاكاة طريقة التأكيد. لا تُدخل رمز قفل هاتفك أو أي بيانات حيوية.</p>
<form method="POST" action="{{ route('employee.neighborhood-attestations.confirm',$attestation) }}">@csrf
<label>طريقة المحاكاة<select name="method" class="form-control"><option value="simulated_fingerprint">محاكاة بصمة الهاتف</option><option value="simulated_device_confirmation">محاكاة تأكيد قفل الهاتف</option></select></label>
<label><input type="checkbox" name="simulation_acknowledged" value="1" required> أفهم أن هذا تأكيد تجريبي فقط</label><button class="btn btn-primary">تنفيذ المحاكاة</button></form></dialog>
@if(session('confirmation_token'))<form method="POST" action="{{ route('employee.neighborhood-attestations.decide',$attestation) }}">@csrf<input type="hidden" name="decision" value="approved"><input type="hidden" name="confirmation_token" value="{{ session('confirmation_token') }}"><button class="btn btn-primary">تسجيل الموافقة وإشعار المواطن</button></form>@endif
<form method="POST" class="card ops-card" action="{{ route('employee.neighborhood-attestations.decide',$attestation) }}">@csrf<input type="hidden" name="decision" value="rejected"><label>سبب عدم الموافقة<textarea name="reason" required maxlength="1500" class="form-control"></textarea></label><button class="btn btn-outline">لا أوافق على اعتماد شهادة السكن</button></form>
@endif
</section>
@endsection
