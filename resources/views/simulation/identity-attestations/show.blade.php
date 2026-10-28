@extends('layouts.public')
@section('title', 'محاكاة المصادقة على الاستمارة')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/identity-attestation.js') }}" defer></script>@endpush
@section('content')
<section class="section"><div class="container attestation-workspace">
    <h1>محاكاة المصادقة على الاستمارة</h1>
    <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="attestation-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="attestation-panel"><dl class="attestation-details"><div><dt>مقدم الطلب</dt><dd>{{ $attestation->citizen_name ?? data_get($attestation->form->data, 'applicant_name') ?: 'غير مسجل' }}</dd></div><div><dt>مرجع الطلب الداخلي</dt><dd dir="ltr">{{ $attestation->form->application?->reference ?? $attestation->form->reference }}</dd></div><div><dt>جهة المصادقة</dt><dd>{{ $attestation->authorityLabel() }}</dd></div><div><dt>المستلم</dt><dd>{{ $attestation->recipient_name ?: 'الشاهدان' }}</dd></div><div><dt>الحالة</dt><dd>{{ $attestation->statusLabel() }}</dd></div></dl></section>
    @if($attestation->status === 'pending')
        @if($attestation->authority_type === 'witnesses')
            <form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}">@csrf
                @foreach([0, 1] as $index)
                    @php($witness = $attestation->form->witnesses->firstWhere('position', $index + 1))
                    <fieldset class="attestation-panel"><legend>الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }}</legend>
                        <dl class="attestation-details">@foreach(['name'=>'الاسم','document_type'=>'نوع الوثيقة','document_number'=>'رقم الوثيقة','issued_place'=>'جهة / مكان الإصدار','issued_at'=>'تاريخ الإصدار'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ data_get($witness, $key) instanceof \DateTimeInterface ? data_get($witness, $key)->format('Y-m-d') : (data_get($witness, $key) ?: 'غير مسجل') }}</dd></div>@endforeach</dl>
                        <div class="attestation-field"><label for="witness-attendance-{{ $index }}">الحضور</label><select id="witness-attendance-{{ $index }}" name="witnesses[{{ $index }}][attendance]" required><option value="">اختر</option><option value="present" @selected(old("witnesses.$index.attendance") === 'present')>حاضر</option><option value="absent" @selected(old("witnesses.$index.attendance") === 'absent')>غائب</option></select></div>
                        <div class="attestation-field"><label for="witness-verification-{{ $index }}">التحقق</label><select id="witness-verification-{{ $index }}" name="witnesses[{{ $index }}][verification]" required><option value="">اختر</option><option value="approved" @selected(old("witnesses.$index.verification") === 'approved')>تم التحقق</option><option value="rejected" @selected(old("witnesses.$index.verification") === 'rejected')>لم يُقبل التحقق</option></select></div>
                        <div class="attestation-field"><label for="witness-note-{{ $index }}">ملاحظة اختيارية</label><textarea id="witness-note-{{ $index }}" name="witnesses[{{ $index }}][note]" maxlength="500">{{ old("witnesses.$index.note") }}</textarea></div>
                    </fieldset>
                @endforeach
                <p>قبول المحاكاة يتطلب حضور الشاهدين والتحقق منهما.</p>
                <div class="attestation-actions"><button class="btn btn-primary" name="decision" value="approved">الموافقة على المصادقة</button><button class="btn btn-outline" type="button" data-attestation-reject-open>رفض الطلب</button></div>
                <dialog class="attestation-rejection-dialog" data-attestation-reject-dialog aria-labelledby="witness-rejection-title"><div class="attestation-dialog-heading"><h2 id="witness-rejection-title">سبب الرفض</h2><button class="attestation-close" type="button" data-attestation-reject-close aria-label="إغلاق">×</button></div><div class="attestation-field"><label for="witness-reason">سبب الرفض</label><textarea id="witness-reason" name="reason" maxlength="1500">{{ old('reason') }}</textarea></div><button class="btn btn-primary" name="decision" value="rejected" type="submit">تأكيد الرفض</button></dialog>
            </form>
        @else
            <div class="attestation-actions"><form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-primary">قبول المصادقة التجريبية</button></form><button class="btn btn-outline" type="button" data-attestation-reject-open>رفض المصادقة</button></div>
            <dialog class="attestation-rejection-dialog" data-attestation-reject-dialog aria-labelledby="rejection-title"><div class="attestation-dialog-heading"><h2 id="rejection-title">سبب رفض المصادقة</h2><button class="attestation-close" type="button" data-attestation-reject-close aria-label="إغلاق">×</button></div><form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}">@csrf<input type="hidden" name="decision" value="rejected"><div class="attestation-field"><label for="rejection-reason">سبب الرفض</label><textarea id="rejection-reason" name="reason" required maxlength="1500">{{ old('reason') }}</textarea></div><button class="btn btn-primary">تأكيد الرفض</button></form></dialog>
        @endif
    @else
        <p class="attestation-panel">مرحلة الاستمارة الحالية: <strong>{{ $attestation->form->statusLabel() }}</strong></p>
        @if($attestation->status === 'approved' && $attestation->security_required)<p class="attestation-warning">الخطوة التالية: إجراء إدارة الأمن الورقي المنفصل. الموافقة التجريبية أعلاه لا تنجز هذا الإجراء ولا تستبدل ختمه.</p>@endif
        @if($attestation->rejection_reason)<p class="attestation-panel">سبب الرفض: {{ $attestation->rejection_reason }}</p>@endif
        @include('components.identity-attestation-stamp', ['attestation'=>$attestation])
    @endif
</div></section>
@endsection
