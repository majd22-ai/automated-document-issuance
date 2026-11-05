@can('simulate', $attestation->form)
@push('scripts')
@once
<script src="{{ asset('js/neighborhood-phone.js') }}?v={{ filemtime(public_path('js/neighborhood-phone.js')) }}" defer></script>
@endonce
@endpush
<div data-neighborhood-phone>
<button type="button" class="btn btn-primary" data-phone-open>محاكاة جوال عاقل الحارة</button>
<dialog class="neighborhood-phone" data-phone-dialog aria-labelledby="phone-title-{{ $attestation->id }}" @if($errors->has('verification_code') || $errors->has('verification_method')) data-auto-open @endif>
    <div class="attestation-dialog-heading"><h2 id="phone-title-{{ $attestation->id }}">محاكاة جوال العاقل</h2><button type="button" class="attestation-close" data-phone-close aria-label="إغلاق محاكاة الجوال">×</button></div>
    <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا. لا تُرسل رسائل SMS ولا تُسجل بصمة حيوية.</p>
    <h3>{{ $attestation->recipient_name }}</h3>
    <p>وقت وصول الإشعار التجريبي: {{ $attestation->notification_created_at?->format('Y-m-d H:i') }}</p>
    <dl class="attestation-details"><div><dt>المواطن</dt><dd>{{ $attestation->citizen_name }}</dd></div><div><dt>مرجع الطلب الداخلي</dt><dd>{{ $attestation->form->application?->reference ?? $attestation->form->reference }}</dd></div><div><dt>المحافظة والمديرية</dt><dd>{{ data_get($attestation->recipient_scope,'governorate') }} — {{ data_get($attestation->recipient_scope,'district') }}</dd></div><div><dt>الحي</dt><dd>{{ data_get($attestation->recipient_scope,'neighborhood') }}</dd></div></dl>
    @if($attestation->status === 'pending')
    <section data-phone-request><h3>طلب مصادقة على إقامة مواطن</h3><p>يوجد طلب جديد للمصادقة على إقامة المواطن في الحي الواقع ضمن مسؤوليتك. يرجى مراجعة بيانات الطلب والتأكد من صحة المعلومات قبل الموافقة على الختم الإلكتروني.</p>
    <button type="button" class="btn btn-primary" data-phone-approve>موافقة على المصادقة والختم</button>
    <button type="button" class="btn btn-outline" data-phone-reject>رفض الطلب</button></section>
    <section data-phone-verification hidden><h3 tabindex="-1" data-phone-verification-title>تأكيد هوية العاقل — محاكاة فقط</h3>
    <form method="POST" action="{{ route('simulation.identity-attestations.decide',$attestation) }}">@csrf<input type="hidden" name="decision" value="approved">
    <div class="attestation-field"><label for="phone-method-{{ $attestation->id }}">طريقة التحقق التجريبية</label><select name="verification_method" id="phone-method-{{ $attestation->id }}" data-phone-method required><option value="fingerprint">محاكاة بصمة الإصبع</option><option value="code" @selected(old('verification_method') === 'code')>رمز جوال تجريبي</option></select>@error('verification_method')<p class="attestation-error">{{ $message }}</p>@enderror</div>
    <div data-phone-code hidden class="attestation-field"><label for="phone-code-{{ $attestation->id }}">رمز التحقق التجريبي</label><input id="phone-code-{{ $attestation->id }}" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" disabled><p>رمز البيئة المحلية: <code>{{ config('identity_card.neighborhood_test_code') ?: 'غير مضبوط؛ استخدم محاكاة البصمة أو اضبط الإعداد' }}</code> — لم يُرسل إلى شبكة الهاتف.</p>@error('verification_code')<p class="attestation-error" role="alert">{{ $message }}</p>@enderror</div>
    @if($errors->has('verification_code'))<p class="attestation-error">فشل التحقق التجريبي؛ لم تعتمد المصادقة.</p>@endif
    <p>عدد محاولات التحقق: {{ $attestation->verification_attempts }} من 5.</p>
    <button type="submit" class="btn btn-primary" data-phone-confirm @disabled($attestation->verification_attempts >= 5)>تأكيد البصمة التجريبية واعتماد المصادقة</button></form></section>
    <section data-phone-rejection hidden><h3>رفض المصادقة</h3><form method="POST" action="{{ route('simulation.identity-attestations.decide',$attestation) }}">@csrf<input type="hidden" name="decision" value="rejected"><div class="attestation-field"><label for="phone-reason-{{ $attestation->id }}">سبب الرفض</label><textarea id="phone-reason-{{ $attestation->id }}" name="reason" required maxlength="1500">{{ old('reason') }}</textarea>@error('reason')<p class="attestation-error">{{ $message }}</p>@enderror</div><button type="submit" class="btn btn-primary">تأكيد رفض الطلب</button></form></section>
    @else
    <p role="status">{{ $attestation->statusLabel() }}</p>
    @if($attestation->verification_source)<p>مصدر التحقق: {{ $attestation->verification_source === 'local_code' ? 'رمز تجريبي محلي' : 'محاكاة بصمة محلية' }}.</p>@endif
    @if($attestation->rejection_reason)<p>سبب الرفض: {{ $attestation->rejection_reason }}</p>@endif
    @include('components.identity-attestation-stamp', ['attestation'=>$attestation])
    @endif
</dialog></div>
@endcan
