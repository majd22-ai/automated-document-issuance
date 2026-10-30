@if($identityForm->submitted_at)
@push('styles')
@once
<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">
@endonce
@endpush
<section class="attestation-panel">
    <h2>المصادقة التجريبية</h2>
    <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
    @can('administer', $identityForm)<p><a href="{{ route('identity-workflow.administration.show',$identityForm) }}">مراجعة البيانات وإعادتها للمواطن للتصحيح عند الحاجة</a></p>@endcan
    @php($attestation = $identityForm->attestation)
    @if($attestation)
        <dl class="attestation-details"><div><dt>الجهة</dt><dd>{{ $attestation->authorityLabel() }}</dd></div><div><dt>المستلم</dt><dd>{{ $attestation->recipient_name ?: 'الشاهدان' }}</dd></div><div><dt>الحالة</dt><dd>{{ $attestation->statusLabel() }}</dd></div><div><dt>المرجع التجريبي</dt><dd dir="ltr">{{ $attestation->reference }}</dd></div></dl>
        @if($attestation->rejection_reason)<p>سبب الرفض: {{ $attestation->rejection_reason }}</p>@endif
        @if($attestation->security_required)<p class="attestation-warning">{{ $attestation->security_status === 'recorded_manually' ? 'سجل الموظف مرجع مراجعة الإجراء الورقي إداريًا؛ لا يمثل ختمًا حكوميًا أو تحققًا آليًا.' : 'إجراء إدارة الأمن الورقي مطلوب بشكل منفصل ولم يُسجل مرجعه بعد.' }}</p>@endif
        @can('simulate', $identityForm)<a class="btn btn-outline" href="{{ route('simulation.identity-attestations.show', $attestation) }}">فتح مساحة المحاكاة للموظف</a>@endcan
        @if($attestation->neighborhood_official_id)
            <p>المحافظة: {{ data_get($attestation->recipient_scope,'governorate') }} · المديرية: {{ data_get($attestation->recipient_scope,'district') }} · الحي: {{ data_get($attestation->recipient_scope,'neighborhood') }}</p>
            @can('simulate', $identityForm)@include('components.neighborhood-phone', ['attestation'=>$attestation])@else<p>طلب المصادقة محفوظ. تظهر محاكاة جوال العاقل للموظف المخوّل فقط؛ ستصلك نتيجة المراجعة في إشعاراتك.</p>@endcan
        @endif
    @else
        @can('chooseAttestation', $identityForm)
        <form method="POST" action="{{ route('citizen.identity-card.attestation.store', $identityForm) }}" data-residence-selects data-officials-url="{{ url('/residence/districts/__DISTRICT__/officials') }}">@csrf
            <input type="hidden" name="authority_type" value="neighborhood">
            @include('components.residence-selects', ['prefix'=>'attestation', 'selectedGovernorate'=>old('governorate_id', \App\Models\ResidenceGovernorate::where('name',data_get($identityForm->data,'residence_governorate'))->value('id')), 'selectedDistrict'=>old('district_id', \App\Models\ResidenceDistrict::where('name',data_get($identityForm->data,'residence_district'))->whereHas('governorate',fn($query)=>$query->where('name',data_get($identityForm->data,'residence_governorate')))->value('id'))])
            <div class="attestation-field"><label for="attestation-official">عاقل الحارة / مسؤول الحي *</label><select id="attestation-official" name="official_id" data-location-official data-selected="{{ old('official_id') }}" required><option value="">اختر المديرية أولًا</option></select>@error('official_id')<p class="attestation-error">{{ $message }}</p>@enderror</div>
            <div class="attestation-field"><label for="attestation-neighborhood">الحي المسؤول عنه العاقل</label><input id="attestation-neighborhood" name="neighborhood" data-location-neighborhood readonly required>@error('neighborhood')<p class="attestation-error">{{ $message }}</p>@enderror</div>
            @error('district_id')<p class="attestation-error">{{ $message }}</p>@enderror
            <p data-location-status role="status"></p><p>يجب أن يطابق نطاق العاقل عنوان إقامتك المحفوظ. إذا لم يظهر عاقل مسجل، راجع المصلحة لإضافة بياناته الصحيحة.</p>
            <button class="btn btn-primary" type="submit" data-attestation-request hidden>طلب مصادقة عاقل الحارة</button>
            <noscript><p>يلزم تفعيل JavaScript لتحميل القوائم المترابطة.</p></noscript>
        </form>
        @else
        <p>لم يُحدد مسار المصادقة بعد. يختاره صاحب الطلب من حسابه في البيئة المحلية.</p>
        @endcan
    @endif
</section>
@endif
