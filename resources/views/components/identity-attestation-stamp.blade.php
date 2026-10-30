@if($attestation && $attestation->status === 'approved' && (!$attestation->neighborhood_official_id || ($attestation->verified_at && in_array($attestation->verification_source,['local_code','local_fingerprint'],true))) && $attestation->form_revision === $attestation->form->revision && !in_array($attestation->form->status, ['rejected','cancelled'], true))
@push('styles')
@once
<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">
@endonce
@endpush
<aside class="attestation-stamp" aria-label="مصادقة تجريبية">
    <strong>مصادقة تجريبية داخل المنصة</strong>
    <p>{{ $attestation->authorityLabel() }}</p>
    @if($attestation->neighborhood_official_id)<p>العاقل: {{ $attestation->recipient_name }} — {{ data_get($attestation->recipient_scope,'neighborhood') }}</p><p>مصدر التحقق: {{ $attestation->verification_source === 'local_code' ? 'رمز تجريبي محلي' : 'محاكاة بصمة محلية' }}</p>@endif
    <p>المصادق: {{ $attestation->decider?->name ?? 'غير مسجل' }}</p>
    <p>التاريخ: {{ $attestation->decided_at?->format('Y-m-d H:i') }}</p>
    <p dir="ltr">{{ $attestation->reference }}</p>
    <small>ليست اعتمادًا رسميًا</small>
</aside>
@endif
