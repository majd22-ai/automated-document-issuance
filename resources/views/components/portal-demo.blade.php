@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/portal-demo.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/portal-demo.js') }}" defer></script>
    @endpush
@endonce
<div data-portal-demo data-screen="{{ $screen ?? 'dashboard' }}" data-request-id="{{ $demoRequestId ?? '' }}">
    <noscript>يرجى تفعيل JavaScript لتجربة الواجهات والبيانات التجريبية.</noscript>
</div>
