@push('scripts')
@once
<script src="{{ asset('js/mobile-attestation-simulator.js') }}?v={{ filemtime(public_path('js/mobile-attestation-simulator.js')) }}" defer></script>
@endonce
@endpush
<section data-ops-view="attestations" data-attestation-simulator data-feed-url="{{ route('simulation.identity-attestations.feed', [], false) }}" data-csrf="{{ csrf_token() }}" hidden>
        <h3>محاكاة إشعارات الجوال</h3>
        <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
        <p data-attestation-feed-status role="status">جارٍ تحميل الإشعارات…</p>
        <ul class="attestation-notifications" data-attestation-notifications></ul>
        <a href="{{ route('simulation.identity-attestations.index') }}" target="_blank" rel="noopener noreferrer">جميع طلبات المصادقة ومراجعة الشاهدين</a>
</section>
