<section data-ops-view="civil-check" hidden>
    <h3>محاكاة التحقق من السجل المدني</h3>
    <p>ابحث في سجلات المحاكاة بالرقم الوطني أو الاسم الرباعي. تظهر الأسماء المقترحة أثناء الكتابة.</p>
    <form class="card ops-card" action="{{ route('integrations.civil-registry-demo.run', [], false) }}" method="post" data-ops-civil-check>
        @csrf
        <label for="civil-check-query">الرقم الوطني أو الاسم الرباعي</label>
        <input id="civil-check-query" class="form-control" name="query" type="search" minlength="2" maxlength="150" dir="auto" autocomplete="off" required>
        <ul class="operations-tools-suggestions" data-civil-suggestions aria-label="الأسماء المقترحة" hidden></ul>
        <p><button type="submit" class="btn btn-primary">تنفيذ المحاكاة</button></p>
        <p data-ops-civil-outcome role="status">{{ match(session('registrySimulationOutcome')) {
            'match' => 'تطابقت البيانات مع السجل التجريبي.',
            'mismatch' => 'الاسم المُدخل لا يطابق السجل التجريبي.',
            'not_found' => 'لم يُعثر على قيد مطابق في السجل التجريبي.',
            'unavailable' => 'خدمة السجل التجريبي غير متاحة.',
            default => '',
        } }}</p>
    </form>
    <div data-ops-civil-records></div>
    <details>
        <summary>بيانات جاهزة للاختبار</summary>
        <div class="operations-tools-grid">
            @foreach(config('civil_registry_demo.records', []) as $number => $record)
                <button type="button" data-ops-civil-number="{{ $number }}" data-ops-civil-name="{{ $record['name'] }}">
                    {{ $record['name'] }} · <bdi>{{ $number }}</bdi> · {{ $record['available'] ? 'متاح' : 'غير متاح' }}
                </button>
            @endforeach
        </div>
    </details>
</section>
