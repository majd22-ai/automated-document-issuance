@extends('layouts.public')
@section('title', 'اعتماد تفعيل حساب المواطن')
@section('content')
@if($citizen->activationRequest)
<div class="container alert alert-info">طلب تفعيل مرسل من المواطن برقم {{ $citizen->activationRequest->id }} بتاريخ {{ $citizen->activationRequest->submitted_at }} — {{ match($citizen->activationRequest->status) { 'approved'=>'تم الاعتماد', 'review_required'=>'يحتاج مراجعة آمنة', default=>'بانتظار المراجعة' } }}</div>
@endif
<section class="section" dir="rtl"><div class="container"><div class="ops-page-head"><h1>اعتماد تفعيل حساب المواطن</h1><p class="ops-help">راجع بيانات الحساب وسجل الإجراءات، ثم استكمل الإقرار والمرجع الرسمي في نموذج الاعتماد.</p></div>@include('operations.messages')
<div class="card ops-card" style="padding:24px"><h2>{{ $citizen->name }}</h2><p>معرّف الحساب: {{ $citizen->id }} — اسم المستخدم: {{ $citizen->username }}</p><p>{{ $citizen->accountStatusLabel() }}</p><p>التفعيل المسجل: {{ $citizen->account_activated_at ? 'نعم' : 'لا' }} — توثيق الهوية العام: {{ $citizen->identity_verified_at ? 'نعم' : 'لا' }}</p></div>
@if(!$citizen->hasActivatedIdentity())
@include('employee.accounts.in-person')

@else<p class="alert alert-success">التفعيل وتوثيق الهوية مكتملان على الحساب الحالي. لا يلزم إجراء تفعيل جديد.</p>@endif
<h2>سجل إجراءات الحساب</h2><div class="card ops-card" style="overflow-x:auto" tabindex="0" role="region" aria-label="جدول السجلات"><table class="table" style="width:100%;text-align:start"><thead><tr><th scope="col">الوقت</th><th scope="col">الموظف</th><th scope="col">الإجراء</th><th scope="col">النتيجة</th><th scope="col">توثيق الهوية في الإقرار</th></tr></thead><tbody>@forelse($confirmations as $confirmation)<tr><td>{{ $confirmation->confirmed_at }}</td><td>{{ $confirmation->actor?->name }}</td><td>{{ $confirmation->action === 'confirm_issuance' ? 'تأكيد إصدار رسمي' : 'اعتماد حساب' }}</td><td>{{ $confirmation->result === 'confirmed' ? 'تم الاعتماد' : 'تعارض — لم تعتمد الهوية' }}</td><td>{{ $confirmation->identity_confirmed ? 'مؤكد في الإقرار' : 'غير مكتمل' }}</td></tr>@empty<tr><td colspan="5">لا توجد إجراءات اعتماد مسجلة.</td></tr>@endforelse</tbody></table></div>{{ $confirmations->links() }}
@foreach($confirmations as $confirmation)
  @if($confirmation->fingerprint_source)
    <p>مصدر اعتماد #{{ $confirmation->id }}: محاكاة بصمة محلية — {{ $confirmation->phone_verification_source === 'local_trial' ? 'رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف' : 'رسالة SMS أُرسلت عبر المزود' }}.</p>
  @endif
@endforeach
</div></section>
@endsection
