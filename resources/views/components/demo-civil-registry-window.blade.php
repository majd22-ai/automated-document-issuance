@php
    $base = url('/employee/demo-civil-registry');
    $personPlaceholder = '__DEMO_PERSON__';
    $sectionPlaceholder = '__DEMO_SECTION__';
    $requestId = $application->id;
@endphp
@push('styles')<link rel="stylesheet" href="{{ asset('css/demo-civil-registry.css') }}?v={{ filemtime(public_path('css/demo-civil-registry.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/demo-civil-registry-fields.js') }}?v={{ filemtime(public_path('js/demo-civil-registry-fields.js')) }}" defer></script><script src="{{ asset('js/demo-civil-registry.js') }}?v={{ filemtime(public_path('js/demo-civil-registry.js')) }}" defer></script>@endpush
<section class="demo-registry-launcher" dir="rtl" aria-label="السجل المدني التجريبي">
  <button type="button" class="btn btn-outline" data-demo-registry-open aria-haspopup="dialog" aria-controls="demo-civil-registry-window">فتح السجل المدني التجريبي</button>
  <span>قراءة فقط للمراجعة، ولا تغيّر حالة الطلب.</span>
</section>
<dialog id="demo-civil-registry-window" class="demo-registry-window" dir="rtl"
  aria-labelledby="demo-registry-title" aria-describedby="demo-registry-warning"
  data-search-url="{{ route('employee.demo-civil-registry.search') }}"
  data-person-url="{{ route('employee.demo-civil-registry.people.show', $personPlaceholder) }}"
  data-section-url="{{ route('employee.demo-civil-registry.people.section', [$personPlaceholder, $sectionPlaceholder]) }}"
  data-request-id="{{ $requestId }}"
  data-suggested-section="{{ $suggestedSection ?? '' }}">
  <header class="demo-registry-window-head" data-demo-registry-drag>
    <div><h2 id="demo-registry-title">السجل المدني التجريبي</h2><span class="demo-registry-badge">بيانات اصطناعية</span></div>
    <div class="demo-registry-window-actions"><button type="button" data-demo-registry-minimize aria-label="تصغير النافذة" title="تصغير">_</button><button type="button" data-demo-registry-close aria-label="إغلاق النافذة" title="إغلاق">×</button></div>
  </header>
  <div class="demo-registry-window-content">
    <p id="demo-registry-warning" class="demo-registry-warning">هذا سجل مدني تجريبي لأغراض المحاكاة والمراجعة الداخلية، ولا يمثل سجلاً حكومياً معتمداً ولا يُستخدم لإثبات الهوية أو صحة الوقائع المدنية.</p>
    <form class="demo-registry-search" data-demo-registry-search novalidate>
      <label for="demo-registry-mode">نوع البحث</label><select id="demo-registry-mode" name="mode"><option value="name">الاسم</option><option value="identifier">المعرف التجريبي</option><option value="document">رقم الوثيقة</option></select>
      <label for="demo-registry-query">بحث</label><input id="demo-registry-query" name="q" type="search" minlength="2" maxlength="100" autocomplete="off" required placeholder="اسم أو معرف تجريبي أو رقم وثيقة">
      <button type="submit" class="btn btn-primary">بحث</button>
    </form>
    <p class="demo-registry-status" data-demo-registry-status role="status" aria-live="polite"></p>
    <div class="demo-registry-results" data-demo-registry-results aria-label="نتائج البحث"></div>
    <div class="demo-registry-profile" data-demo-registry-profile hidden>
      <button type="button" class="demo-registry-back" data-demo-registry-back>العودة إلى النتائج</button>
      <div class="demo-registry-person" data-demo-registry-person></div>
      <nav class="demo-registry-sections" data-demo-registry-sections aria-label="أقسام السجل"></nav>
      <div class="demo-registry-records" data-demo-registry-records aria-live="polite"></div>
    </div>
  </div>
</dialog>
