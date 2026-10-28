<section class="section" id="civil-registry-requirements" aria-labelledby="civil-guide-title"><div class="container">
<div class="section-header"><h2 id="civil-guide-title">{{ $guide['title'] }} — الوثائق المطلوبة</h2><p>المحتوى من بطاقة الخدمة المصورة المرفقة بالمشروع.</p></div>
<div class="grid grid-2">
@foreach($guide['sections'] as $heading => $items)
<article class="card"><h3>{{ $heading }}</h3><ul class="requirements-list">@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul></article>
@endforeach
<article class="card"><h3>مدة إنجاز الخدمة</h3><ul class="requirements-list">@foreach($guide['duration'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
<article class="card"><h3>الرسوم المقررة في الصورة</h3><ul class="requirements-list">@foreach($guide['fees'] as $item)<li>{{ $item }}</li>@endforeach</ul><p>تُعرض هنا رسوم المرجع المصور؛ الرسوم المسجلة للطلب تظهر في معلومات الخدمة عند التقديم.</p></article>
</div></div></section>
