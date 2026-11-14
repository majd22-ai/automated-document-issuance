@php
    $initialTasks = app(\App\Services\Identity\InitialIdentityReview::class)->tasks($application->identityCardForm);
    $identityForm = $application->identityCardForm;
@endphp
<section aria-label="مراجعة الخطوات ٤ و٥ و٦">
<h2>ملف معاملة الهوية — المراجعة الأولية</h2>
<p>ابدأ باستلام الطلب، ثم راجع الصورة والشاهدين ومصادقة العاقل. البيانات التي تحقق منها النظام موجودة في التفاصيل أدناه. القبول المبدئي متاح بعد اكتمال المهام الثلاث.</p>
@if($application->status === 'submitted')
@can('transition', [$application, \App\Enums\ServiceRequestStatus::UnderReview])
<form method="POST" action="{{ route('employee.applications.decisions', $application) }}">@csrf
<input type="hidden" name="action" value="start"><input type="hidden" name="decision_token" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $application->review_version }}"><input type="hidden" name="expected_rules_version" value="{{ $application->service->rules_version }}"><input type="hidden" name="expected_fingerprint" value="{{ $fingerprint }}">
<button class="btn btn-primary">استلام الطلب وبدء المراجعة</button></form>
@else<p>الطلب مسند إلى موظف آخر؛ القرارات متاحة للمختص بالطلب.</p>@endcan
@endif
@foreach($initialTasks as $taskKey => $task)
<article class="card ops-card" style="padding:1.5rem;margin-block:1rem" id="identity-task-{{ $taskKey }}">
<h3>{{ $task['label'] }}</h3>
<p><strong>{{ $task['approved'] ? 'مكتملة' : ($task['ready'] ? 'بانتظار قرار الموظف' : 'أدلة ناقصة أو غير متطابقة') }}</strong></p>
<p>{{ $task['message'] }}</p>
@if($task['issues'])<ul>@foreach($task['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
@if($taskKey === 'photo' && $identityForm->photo?->intact())
<a href="{{ route('citizen.identity-card.photo', $identityForm) }}" target="_blank" rel="noopener"><img src="{{ route('citizen.identity-card.photo', $identityForm) }}" alt="الصورة المرفوعة للمراجعة" style="max-width:220px;max-height:240px;object-fit:contain"></a>
<p>قرار الموافقة هنا لجودة الصورة وملاءمتها؛ مطابقة صاحبها تُستكمل عند الحضور.</p>
@endif
@if($task['comparisons'])
<p>مرجع الوثيقة في سجل المحاكاة: {{ $task['source_id'] ?? 'غير موجود' }} — وقت المقارنة: {{ $task['source_checked_at'] }}</p>
<div style="overflow-x:auto"><table class="table"><thead><tr><th scope="col">البيان</th><th scope="col">المرفوع</th><th scope="col">سجل المحاكاة</th><th scope="col">النتيجة</th></tr></thead><tbody>
@foreach($task['comparisons'] as $comparison)<tr><th scope="row">{{ $comparison['label'] }}</th><td>{{ $comparison['submitted'] ?: 'ناقص' }}</td><td>{{ $comparison['source'] ?: 'غير متاح' }}</td><td>{{ $comparison['match'] ? 'متطابق' : 'ناقص أو مختلف' }}</td></tr>@endforeach
</tbody></table></div>
@php($witnessPosition = (int) substr($taskKey, -1))
@if($identityForm->witnesses->firstWhere('position', $witnessPosition)?->selfieIntact())
<a href="{{ route('citizen.identity-card.witness-selfie', [$identityForm, $witnessPosition]) }}" target="_blank" rel="noopener">عرض صورة الشاهد مع وثيقته</a>
@else<p>صورة الشاهد مفقودة أو تعذر التحقق من سلامتها.</p>@endif
<p>رقم التواصل في السجل: {{ $task['source_phone'] ?: 'غير متاح' }}. سجل الموظفين التجريبي منفصل، ولا توجد مطابقة تلقائية بين السجلين بالاسم.</p>
@endif
@if($taskKey === 'attestation')<p>مرجع المصادقة: {{ $task['reference'] ?: 'غير موجود' }} — وقت التحقق: {{ $task['verified_at'] ?: 'غير متاح' }}</p>@endif
@if($task['decision'])<p>آخر قرار: {{ ($task['decision']->details['outcome'] ?? '') === 'approved' ? 'موافقة' : 'يحتاج استكمالًا' }} — {{ $task['decision']->actor?->name }} — {{ $task['decision']->decided_at?->format('Y-m-d H:i') }}</p>
@if(!empty($task['decision']->details['note']))<p>{{ $task['decision']->details['note'] }}</p>@endif
@if(!empty($task['decision']->details['consent_reference']))
<p>دليل موافقة الشاهد المسجل: {{ ($task['decision']->details['consent_method'] ?? '') === 'signed_statement' ? 'إقرار موقع' : 'تأكيد مستقل موثق' }} — المرجع: {{ $task['decision']->details['consent_reference'] }}</p>
@endif
@endif
@can('resolveReview', $application)
<form method="POST" action="{{ route('employee.applications.identity-step', $application) }}">@csrf
<input type="hidden" name="task" value="{{ $taskKey }}"><input type="hidden" name="decision_token" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="fingerprint" value="{{ $task['fingerprint'] }}">
@if(str_starts_with($taskKey, 'witness_'))
<label for="consent-method-{{ $taskKey }}">دليل موافقة الشاهد المستقل</label>
<select class="form-control" name="consent_method" id="consent-method-{{ $taskKey }}"><option value="">اختر بعد التحقق من الدليل</option><option value="signed_statement">إقرار شهادة موقع تم فحصه</option><option value="independent_confirmation">تأكيد مستقل من الشاهد تم توثيقه</option></select>
<label for="consent-reference-{{ $taskKey }}">مرجع الإقرار أو سجل التأكيد</label><input class="form-control" id="consent-reference-{{ $taskKey }}" name="consent_reference" maxlength="200">
<p>هذا توثيق إداري من الموظف بعد فحص الدليل؛ النظام لا يرسل تأكيدًا للشاهد تلقائيًا. لا يُطلب حضوره افتراضيًا.</p>
@endif
<label for="note-{{ $taskKey }}">ملاحظة المراجعة أو سبب الاستكمال</label><textarea class="form-control" name="note" id="note-{{ $taskKey }}" maxlength="1000"></textarea>
<label><input type="checkbox" name="confirmed" value="1"> أؤكد إتمام الفحص اليدوي للأدلة المذكورة</label>
<div style="margin-block-start:1rem"><button class="btn btn-primary" name="outcome" value="approved" @disabled(!$task['ready'])>اعتماد مراجعة العنصر</button> <button class="btn btn-outline" name="outcome" value="correction_required">تسجيل نقص في العنصر</button></div>
</form>
@endcan
</article>
@endforeach
<p>تسجيل النقص يحفظ الملاحظة داخليًا. لإشعار المواطن وفتح تصحيح العنصر استخدم «إعادة الطلب للاستكمال» أدناه وحدد العنصر المطلوب فقط.</p>
</section>
