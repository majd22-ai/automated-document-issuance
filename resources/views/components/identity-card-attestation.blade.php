@if($identityForm->submitted_at)
@push('styles')
@once
<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">
@endonce
@endpush
<section class="attestation-panel">
    <h2>المصادقة التجريبية</h2>
    <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
    @php($attestation = $identityForm->attestation)
    @if($attestation)
        <dl class="attestation-details"><div><dt>الجهة</dt><dd>{{ $attestation->authorityLabel() }}</dd></div><div><dt>المستلم</dt><dd>{{ $attestation->recipient_name ?: 'الشاهدان' }}</dd></div><div><dt>الحالة</dt><dd>{{ $attestation->statusLabel() }}</dd></div><div><dt>المرجع التجريبي</dt><dd dir="ltr">{{ $attestation->reference }}</dd></div></dl>
        @if($attestation->rejection_reason)<p>سبب الرفض: {{ $attestation->rejection_reason }}</p>@endif
        @if($attestation->security_required)<p class="attestation-warning">{{ $attestation->security_status === 'recorded_manually' ? 'سجل الموظف مرجع مراجعة الإجراء الورقي إداريًا؛ لا يمثل ختمًا حكوميًا أو تحققًا آليًا.' : 'إجراء إدارة الأمن الورقي مطلوب بشكل منفصل ولم يُسجل مرجعه بعد.' }}</p>@endif
        @can('simulate', $identityForm)<a class="btn btn-outline" href="{{ route('simulation.identity-attestations.show', $attestation) }}">فتح مساحة المحاكاة للموظف</a>@endcan
    @else
        @can('chooseAttestation', $identityForm)
        <form method="POST" action="{{ route('citizen.identity-card.attestation.store', $identityForm) }}">@csrf
            <div class="attestation-field"><label for="attestation-authority">جهة المصادقة</label><select id="attestation-authority" name="authority_type" required><option value="">اختر الجهة</option>@foreach(['workplace'=>'جهة العمل','neighborhood'=>'عاقل الحارة','witnesses'=>'الشاهدان'] as $value=>$label)<option value="{{ $value }}" @selected(old('authority_type') === $value)>{{ $label }}</option>@endforeach</select>@error('authority_type')<p class="attestation-error">{{ $message }}</p>@enderror</div>
            <div class="attestation-field"><label for="attestation-recipient">اسم المستلم لدى جهة العمل أو عاقل الحارة</label><input id="attestation-recipient" name="recipient_name" value="{{ old('recipient_name') }}" maxlength="150" aria-describedby="attestation-recipient-help"><small id="attestation-recipient-help">مطلوب عند اختيار جهة العمل أو عاقل الحارة. لا يلزم عند اختيار الشاهدين.</small>@error('recipient_name')<p class="attestation-error">{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary" type="submit">طلب مصادقة تجريبية</button>
        </form>
        @else
        <p>لم يُحدد مسار المصادقة بعد. يختاره صاحب الطلب من حسابه في البيئة المحلية.</p>
        @endcan
    @endif
</section>
@endif
