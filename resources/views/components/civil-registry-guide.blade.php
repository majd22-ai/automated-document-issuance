<section class="section" id="civil-registry-requirements" aria-labelledby="civil-guide-title"><div class="container">
<div class="section-header"><h2 id="civil-guide-title">{{ $guide['title'] }} — معلومات أولية عن الوثائق</h2><p>تفريغ أولي من صورة بطاقة متاحة في المشروع؛ لم يتوفر رقم إصدارها أو تاريخها أو مرجع اعتمادها الرسمي.</p></div>
<p class="gov-demo-note" role="note">هذه القائمة غير مؤكدة الحداثة وليست اعتمادًا رسميًا. راجع مصلحة الأحوال المدنية قبل تجهيز الوثائق أو زيارة الفرع. لن نعرض الرسوم أو مدة الإنجاز الواردة في الصورة حتى توثيق مرجع رسمي حديث.</p>
<div class="grid grid-2">
@foreach($guide['sections'] as $heading => $items)
<article class="card"><h3>{{ $heading }}</h3><ul class="requirements-list">@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul></article>
@endforeach
<article class="card"><h3>الرسوم ومدة الإنجاز</h3><p>لم يتم التحقق من القيم الحالية بمرجع رسمي منشور؛ يرجى تأكيدها لدى الجهة المختصة.</p></article>
<article class="card"><h3>المصدر الرسمي المتاح</h3><p>الموقع الإلكتروني لمصلحة الأحوال المدنية والسجل المدني يعرّف بخدمات المصلحة والمواعيد والاستعلام، ولا تعرض الصفحة التي تمت مراجعتها تفاصيل هذه المتطلبات أو الرسوم.</p><a href="https://www.cra.gov.ye/home" target="_blank" rel="noopener noreferrer">زيارة موقع المصلحة</a></article>
</div></div></section>
