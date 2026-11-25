@php
    $initialTasks = app(\App\Services\Identity\InitialIdentityReview::class)->tasks($application->identityCardForm);
    $identityForm = $application->identityCardForm;
@endphp
<section aria-label="المراجعة الأولية لمعاملة الهوية">
<h2>ملف معاملة الهوية — المراجعة الأولية</h2>
@if($identityForm->isRenewal())<p>ابدأ باستلام طلب التجديد، ثم راجع الصورة الحديثة وصورة البطاقة السابقة مقابل السجل. القبول المبدئي يتطلب إكمال المهمتين.</p>@else<p>ابدأ باستلام الطلب، ثم راجع الصورة الشخصية وبيانات الشاهدين. القبول المبدئي متاح بعد اكتمال المهام الثلاث.</p>@endif
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
@if($taskKey === 'photo')
    @if($identityForm->photo?->intact())
        <x-review-image-dialog id="review-citizen-photo" :source="route('citizen.identity-card.photo', $identityForm)"
                               title="الصورة الشخصية للمواطن" label="تكبير الصورة الشخصية" :thumbnail="true" />
    @endif
    <dl class="review-photo-identity">
        <dt>اسم المواطن</dt><dd>{{ $registryIdentity?->name ?? (data_get($identityForm->data, 'applicant_name') ?: $application->citizen->name) }}</dd>
        @if($nationalId = $registryIdentity?->national_id ?: $application->citizen->national_id)
            <dt>الرقم الوطني</dt><dd><bdi>{{ $nationalId }}</bdi></dd>
        @else
            <dt>اسم المستخدم</dt><dd><bdi>{{ $application->citizen->username ?: 'غير مسجل' }}</bdi></dd>
        @endif
    </dl>
    <p>قرار الموافقة هنا لجودة الصورة وملاءمتها. مطابقة صاحبها تُستكمل عند الحضور.</p>
@endif
@if($taskKey === 'previous_card')
@php($renewalCopy = app(\App\Services\Identity\IdentityRenewals::class)->cardCopy($identityForm))
@php($renewalPrevious = app(\App\Services\Identity\IdentityRenewals::class)->previous($identityForm))
<p>رقم البطاقة المسجلة: <bdi>{{ $renewalPrevious?->document_number }}</bdi> — الإصدار: {{ $renewalPrevious?->issued_at?->format('Y-m-d') }} — الانتهاء: {{ $renewalPrevious?->expires_at?->format('Y-m-d') }}</p>
@if($renewalCopy)<a target="_blank" rel="noopener" href="{{ route('attachments.preview',$renewalCopy) }}">عرض صورة البطاقة السابقة</a>@endif
@endif
@if($task['comparisons'])
<p>مرجع الوثيقة في سجل المحاكاة: {{ $task['source_id'] ?? 'غير موجود' }} — وقت المقارنة: {{ $task['source_checked_at'] }}</p>
<div style="overflow-x:auto"><table class="table"><thead><tr><th scope="col">البيان</th><th scope="col">المرفوع</th><th scope="col">سجل المحاكاة</th><th scope="col">النتيجة</th></tr></thead><tbody>
@foreach($task['comparisons'] as $comparison)<tr><th scope="row">{{ $comparison['label'] }}</th><td>{{ $comparison['submitted'] ?: 'ناقص' }}</td><td>{{ $comparison['source'] ?: 'غير متاح' }}</td><td>{{ $comparison['match'] ? 'متطابق' : 'ناقص أو مختلف' }}</td></tr>@endforeach
</tbody></table></div>
@php($witnessPosition = (int) substr($taskKey, -1))
@if($identityForm->witnesses->firstWhere('position', $witnessPosition)?->selfieIntact())
<x-review-image-dialog :id="'review-witness-photo-'.$witnessPosition"
                       :source="route('citizen.identity-card.witness-selfie', [$identityForm, $witnessPosition])"
                       :title="'صورة الشاهد '.$witnessPosition.' مع وثيقته'" label="عرض صورة الشاهد مع وثيقته" :side="true" />
@else<p>صورة الشاهد مفقودة أو تعذر التحقق من سلامتها.</p>@endif
<p>رقم التواصل في السجل: {{ $task['source_phone'] ?: 'غير متاح' }}. سجل الموظفين التجريبي منفصل، ولا توجد مطابقة تلقائية بين السجلين بالاسم.</p>
@endif
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
<label for="consent-reference-{{ $taskKey }}">مرجع الإقرار أو سجل التأكيد</label>
<input class="form-control" id="consent-reference-{{ $taskKey }}" name="consent_reference" maxlength="200" value="{{ old('task') === $taskKey ? old('consent_reference') : '' }}">
<p>هذا توثيق إداري من الموظف بعد فحص الدليل؛ النظام لا يرسل تأكيدًا للشاهد تلقائيًا. لا يُطلب حضوره افتراضيًا.</p>
@endif
<label for="note-{{ $taskKey }}">ملاحظة المراجع أو سبب الاستكمال (اختياري)</label><textarea class="form-control" name="note" id="note-{{ $taskKey }}" maxlength="1000">{{ old('task') === $taskKey ? old('note') : '' }}</textarea>
<label><input type="checkbox" name="confirmed" value="1"> أؤكد إتمام الفحص اليدوي للأدلة المذكورة</label>
<div style="margin-block-start:1rem"><button class="btn btn-primary" name="outcome" value="approved" @disabled(!$task['ready'])>اعتماد مراجعة العنصر</button> <button class="btn btn-outline" name="outcome" value="correction_required">تسجيل نقص في العنصر</button></div>
</form>
@endcan
</article>
@endforeach
<p>تسجيل النقص يحفظ الملاحظة داخليًا. لإشعار المواطن وفتح تصحيح العنصر استخدم «إعادة الطلب للاستكمال» أدناه وحدد العنصر المطلوب فقط.</p>
</section>
