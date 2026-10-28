@push('styles')
@once
<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">
@endonce
@endpush
@push('scripts')
@once
<script src="{{ asset('js/mobile-attestation-simulator.js') }}" defer></script>
@endonce
@endpush
<div class="attestation-mobile" data-attestation-simulator data-feed-url="{{ route('simulation.identity-attestations.feed', [], false) }}" data-csrf="{{ csrf_token() }}">
    <button class="attestation-mobile-toggle" type="button" data-attestation-toggle aria-haspopup="dialog" aria-controls="attestation-mobile-dialog"><x-icon name="bell" /> محاكاة إشعارات الجوال <span data-attestation-count aria-label="إشعارات غير مقروءة">0</span></button>
    <dialog id="attestation-mobile-dialog" class="attestation-mobile-dialog" aria-labelledby="attestation-mobile-title">
        <div class="attestation-dialog-heading"><h2 id="attestation-mobile-title">محاكاة إشعارات الجوال</h2><button type="button" data-attestation-close class="attestation-close" aria-label="إغلاق">×</button></div>
        <p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
        <p data-attestation-feed-status role="status">جارٍ تحميل الإشعارات…</p>
        <ul class="attestation-notifications" data-attestation-notifications></ul>
        <a href="{{ route('simulation.identity-attestations.index') }}">جميع طلبات المصادقة ومراجعة الشاهدين</a>
    </dialog>
</div>
