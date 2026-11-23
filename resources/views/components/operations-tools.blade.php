@php
    $operator = auth()->user();
    $adminTools = $operator->role === 'admin';
    $restricted = \App\Models\NeighborhoodOfficial::residenceOnly($operator);
    $demoRegistry = !$restricted && $operator->can('viewAnyDemoCivilRegistry', \App\Models\User::class);
    $mockRegistry = $adminTools && \App\Services\Registration\MockCivilSystems::enabled();
    $local = app()->environment('local', 'testing');
    $postal = $adminTools && $local && config('postal_network.enabled') && Route::has('admin.postal-network.index');
    $fingerprint = !$restricted && \App\Services\FingerprintService::canUse($operator);
    if ($demoRegistry || $mockRegistry || $postal || $fingerprint) {
        $tables = \Illuminate\Support\Facades\Schema::getTableListing(null, false);
        $demoRegistry = $demoRegistry && !array_diff(\App\Http\Controllers\SimulationCenterController::DEMO_TABLES, $tables);
        $mockRegistry = $mockRegistry && !array_diff(\App\Services\MockCivilRegistry::TABLES, $tables);
        $postal = $postal && !array_diff(['postal_offices', 'postal_collectors'], $tables);
        $fingerprint = $fingerprint && !array_diff(['identity_card_forms', 'identity_fingerprint_operations'], $tables);
    }
    $simulation = false;
    if (!$restricted && \App\Services\Integrations\LocalCivilRegistryProvider::enabled()) {
        $service = \App\Models\GovernmentService::where('code', 'identity-first-issue')->value('id');
        $simulation = $adminTools || $operator->canReviewService($service, 'can_verify') || $operator->canReviewService($service, 'can_activate');
    }
    $verify = !$adminTools && !$restricted && app(\App\Services\Workspace\EmployeeWorkspace::class)->serviceIds($operator, ['can_verify']) !== [];
    $attestations = !$restricted && \App\Services\IdentityAttestations::canUseSimulation($operator);
    $review = request()->routeIs('employee.applications.review') ? ($application ?? null) : null;
    $mockSections = [
        'directory' => 'السجل المدني', 'civil' => 'الأحوال المدنية', 'card' => 'البطاقة الشخصية',
        'birth' => 'سجل المواليد', 'family' => 'الأسرة والقيد العائلي', 'identity' => 'الهوية ومطابقة الوجه',
        'notifications' => 'رموز التحقق والإشعارات', 'documents' => 'الوثائق المدنية الإضافية',
    ];
@endphp
@if($operator->is_active)
@push('scripts')
<script src="{{ asset('js/operations-tools.js') }}?v={{ filemtime(public_path('js/operations-tools.js')) }}" defer></script>
@endpush
<button type="button" class="operations-tools-launcher" data-ops-open="home" aria-controls="operations-tools" aria-expanded="false">
    المحاكاة والأنظمة المتكاملة
</button>
<aside id="operations-tools" class="operations-tools" dir="rtl" aria-labelledby="operations-tools-title" hidden>
    <div class="operations-tools-resize" data-ops-resize role="separator" aria-label="عرض لوحة المحاكاة" aria-controls="operations-tools" aria-orientation="vertical" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" tabindex="0"></div>
    <header class="operations-tools-header">
        <h2 id="operations-tools-title" tabindex="-1">المحاكاة والأنظمة المتكاملة</h2>
        <div>
            <button type="button" class="btn btn-outline" data-ops-size>ملء الشاشة</button>
            <button type="button" class="btn btn-outline" data-ops-close>إغلاق</button>
        </div>
    </header>
    <div class="operations-tools-body">
        <button type="button" class="btn btn-outline" data-ops-back hidden>العودة إلى الأنظمة</button>
        <div data-ops-view="home">
            <h3>أنظمة المحاكاة</h3>
            <div class="operations-tools-grid">
                @if($demoRegistry || $mockRegistry)
                    <button type="button" data-ops-view-button="{{ $mockRegistry ? 'mock' : 'demo' }}">محاكاة أنظمة الأحوال المدنية</button>
                @endif
                @if($demoRegistry && $mockRegistry)
                    <button type="button" data-ops-view-button="demo">السجل المدني التجريبي المستقل</button>
                @endif
                @if($postal)
                    <a href="{{ route('admin.postal-network.index') }}" target="_blank" rel="noopener noreferrer">شبكة البريد التجريبية</a>
                @endif
                @if($fingerprint)
                    <a href="{{ route('identity-workflow.reception') }}" target="_blank" rel="noopener noreferrer">محاكاة البصمة وإجراءات البطاقة</a>
                    @if(\App\Services\FingerprintService::canUse($operator, 'can_approve'))
                        <a href="{{ route('identity-workflow.administration.index') }}" target="_blank" rel="noopener noreferrer">المراجعة الإدارية التجريبية</a>
                    @endif
                @endif
                @if($simulation)
                    <a href="{{ route('integrations.civil-registry-demo') }}" target="_blank" rel="noopener noreferrer">تجربة التحقق من السجل المدني</a>
                @endif
                @if($attestations)
                    @include('components.mobile-attestation-simulator', ['inTools' => true])
                @endif
            </div>
            @if($adminTools || $verify)
                <h3>الأنظمة المتكاملة</h3>
                <div class="operations-tools-grid">
                    @if($adminTools)
                        <a href="{{ route('admin.integrations.index') }}" target="_blank" rel="noopener noreferrer">مراقبة التكامل الإلكتروني</a>
                    @else
                        <a href="{{ route('employee.verifications.index') }}" target="_blank" rel="noopener noreferrer">التحقق الإلكتروني للطلبات</a>
                    @endif
                </div>
            @endif
            @if(!$adminTools && !$verify && !$demoRegistry && !$fingerprint && !$simulation && !$attestations)
                <p>لا توجد أدوات محاكاة أو تكامل متاحة بصلاحياتك الحالية.</p>
            @endif
        </div>
        @if($mockRegistry)
            <section data-ops-view="mock" data-ops-registry="{{ route('admin.mock-civil-registry.index', [], false) }}" hidden>
                <h3>محاكاة أنظمة الأحوال المدنية</h3>
                <div class="operations-tools-grid" aria-label="أنظمة السجل المدني الثمانية">
                    @foreach($mockSections as $key => $title)
                        <button type="button" data-ops-section="{{ $key }}" aria-pressed="false">{{ $title }}</button>
                    @endforeach
                </div>
                <p data-ops-status role="status"></p>
                <div data-ops-registry-content></div>
                <div data-ops-notifications hidden>
                    <p>رموز التسجيل تُعالج داخل مسار التسجيل. سجل المواطن لا يعرض رموز التحقق أو حالة محاولة التسجيل الفردية.</p>
                    @if($attestations)
                        <p><a href="{{ route('simulation.identity-attestations.index') }}" target="_blank" rel="noopener noreferrer">عرض حالة إشعارات المصادقة التجريبية</a></p>
                    @endif
                </div>
            </section>
        @endif
        @if($demoRegistry)
            <section data-ops-view="demo" hidden>
                <h3 id="demo-registry-title">السجل المدني التجريبي المستقل</h3>
                @if($adminTools)
                    <p><a href="{{ route('admin.demo-civil-registry.index') }}" target="_blank" rel="noopener noreferrer">إدارة السجل المستقل</a></p>
                @endif
                @include('components.demo-civil-registry-window', ['inline' => true, 'application' => $review, 'suggestedSection' => $registrySuggestedSection ?? null])
            </section>
        @endif
    </div>
</aside>
@endif
