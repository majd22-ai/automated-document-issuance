@if(\App\Services\Identity\IdentityJourney::applies($application))
@php
  $journeyForm = $application->identityCardForm()->first();
  $journey = app(\App\Services\Identity\IdentityJourney::class);
  $journeyCharge = $application->charge()->with('postalCollection')->first();
  $journeyPaid = $journey->paid($application);
  $journeyStaff = auth()->user()->can('recordOperations', $application);
  $journeyEditable = $journeyStaff && $application->status==='accepted' && $journeyForm->arrived_at && $journeyForm->status!=='demo_completed' && ! $journeyForm->operations()->where('form_revision',$journeyForm->revision)->where('result','success')->exists();
@endphp
<section class="card ops-card identity-journey" style="padding:1.5rem;margin-block:1rem" aria-label="ملف معاملة الهوية">
<h2>ملف معاملة الهوية — {{ $application->reference }}</h2><p>{{ $application->statusLabel() }} — {{ $journeyForm->statusLabel() }}</p>
<p>القبول المبدئي يسبق السداد والزيارة، والاعتماد النهائي يسبق الإصدار، ويكتمل الطلب بعد التسليم الموثق.</p>
<h3>الفاتورة والتحصيل البريدي</h3>
@if($journeyCharge)<p>رقم الفاتورة: <bdi>{{ $journeyCharge->invoice_number }}</bdi> — {{ $journeyCharge->amount }} {{ $journeyCharge->currency }} — {{ $journeyCharge->statusLabel() }}</p>
@if($journeyCharge->postalCollection)<p>سند البريد: <bdi>{{ $journeyCharge->postalCollection->receipt_reference }}</bdi> — {{ $journeyCharge->postalCollection->collected_at->format('Y-m-d H:i') }}</p>@endif
@else<p>تصدر الفاتورة تلقائيًا بعد القبول المبدئي.</p>@endif
<h3>موعد الحضور الواحد</h3>
@foreach($application->appointments()->with('slot')->latest('id')->get() as $journeyBooking)<p>{{ $journeyBooking->slot?->center }} — {{ $journeyBooking->slot?->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — {{ $journeyBooking->statusLabel() }}</p>@endforeach
<p>أحضر نتيجة فحص الدم الذي أُجري خارج مقر الإصدار. لا يلزم حجز منفصل للبصمة أو التصوير أو الفحص.</p>
@if($journeyStaff && $application->status==='accepted' && $journeyPaid && ! $journeyForm->arrived_at)
<p><a class="btn btn-outline" href="{{ route('employee.applications.operations',$application->id) }}">تحديد موعد الحضور أو إعادة جدولته</a></p>
@endif
@if($journeyStaff && $application->status==='accepted' && $journeyPaid)
<p><a class="btn btn-outline" href="{{ route('identity-workflow.reception',['reference'=>$application->reference]) }}">الاستقبال وتسجيل الحضور</a></p>
@endif
<p>الحضور: {{ $journeyForm->arrived_at?->format('Y-m-d H:i') ?? 'لم يُسجل' }}</p>
<h3>أدلة الزيارة</h3>
@foreach(['blood_result'=>'نتيجة فحص الدم الخارجي','card_photo'=>'صورة الوجه المخصصة للبطاقة'] as $journeyKind=>$journeyLabel)
@php($journeyEvidence = $journey->evidence($journeyForm,$journeyKind))
<article style="border-block-start:1px solid #ddd;padding-block:1rem"><h4>{{ $journeyLabel }}</h4>
@if($journeyEvidence)<p>الحالة: {{ ['pending'=>'بانتظار المراجعة','approved'=>'مقبول','rejected'=>'مرفوض','requires_correction'=>'يحتاج إلى استكمال'][$journeyEvidence->status] ?? $journeyEvidence->status }} — {{ $journeyEvidence->reason }}</p><a target="_blank" rel="noopener" href="{{ route('identity-workflow.evidence.file',$journeyEvidence) }}">عرض الملف الخاص</a>
@if($journeyEditable && $journeyEvidence->status==='pending')
<form method="POST" action="{{ route('identity-workflow.evidence.review',$journeyEvidence) }}">@csrf<input type="hidden" name="version" value="{{ $journeyForm->version }}"><label>نتيجة المراجعة<select class="form-control" name="status" required><option value="approved">قبول</option><option value="rejected">رفض</option><option value="requires_correction">طلب استكمال</option></select></label><label>سبب الرفض أو الاستكمال<input class="form-control" name="reason" maxlength="1000"></label><button class="btn btn-outline">توثيق المراجعة</button></form>
@endif
@else<p>لم يُرفق دليل لهذه النسخة.</p>@endif
@if($journeyEditable)
<form method="POST" enctype="multipart/form-data" action="{{ route('identity-workflow.evidence.store',$journeyForm) }}" @if($journeyKind==='card_photo') data-card-camera @endif>@csrf<input type="hidden" name="version" value="{{ $journeyForm->version }}"><input type="hidden" name="kind" value="{{ $journeyKind }}"><label>{{ $journeyLabel }}<input class="form-control" type="file" name="file" accept="{{ $journeyKind==='card_photo'?'image/jpeg,image/png':'image/jpeg,image/png,application/pdf' }}" @if($journeyKind==='card_photo') capture="user" @endif required></label>
@if($journeyKind==='card_photo')<button type="button" class="btn btn-outline" data-camera-start>فتح الكاميرا</button><video autoplay playsinline muted hidden style="max-width:100%;width:360px"></video><button type="button" class="btn btn-outline" data-camera-capture hidden>التقاط الصورة</button><p role="status" data-camera-status></p>@endif
<button class="btn btn-primary">حفظ دليل جديد مع إبقاء التاريخ</button></form>
@endif</article>
@endforeach
<h3>الشاهدان</h3><p>{{ $journeyForm->witness_attendance_reason ? 'طُلب حضورهما بسبب: '.$journeyForm->witness_attendance_reason : 'تراجع بياناتهما ومصادقاتهما المقدمة؛ لا يُطلب حضور إضافي افتراضيًا.' }}</p>
@if($journeyStaff && in_array($application->status,['submitted','under_review','accepted']) && ! $journeyForm->operations()->where('form_revision',$journeyForm->revision)->where('result','success')->exists())
<details><summary>طلب حضور استثنائي للشاهدين أو تسجيل التحقق</summary><form method="POST" action="{{ route('identity-workflow.witnesses',$journeyForm) }}">@csrf<input type="hidden" name="version" value="{{ $journeyForm->version }}"><label>السبب أو مرجع فحص الوثائق<input class="form-control" name="reason" minlength="3" maxlength="1000" required></label>@if($journeyForm->arrived_at && $journeyForm->witness_attendance_reason)<label><input type="checkbox" name="present_and_verified" value="1"> حضر الشاهدان وفحصت وثائقهما وقبلتها</label>@endif<button class="btn btn-outline">حفظ إجراء الشاهدين</button></form></details>
@endif
<h3>البصمة والمراجعة النهائية</h3><p>{{ $journeyForm->fingerprintStatusLabel() }}</p>
@foreach($journeyForm->operations()->latest('id')->get() as $journeyOperation)<p>{{ $journeyOperation->executed_at->format('Y-m-d H:i') }} — {{ $journeyOperation->resultLabel() }} — {{ $journeyOperation->sourceLabel() }}</p>@endforeach
@if($journeyStaff && $journeyForm->arrived_at && ! $journey->evidenceReasons($journeyForm) && $application->status==='accepted' && in_array($journeyForm->status,['pending_fingerprint_attendance','fingerprint_failed']))<a class="btn btn-outline" href="{{ route('identity-workflow.device',$journeyForm) }}">إجراء محاكاة البصمة</a>@endif
@can('administer',$journeyForm)@if(in_array($journeyForm->status,['pending_administrative_review','returned_to_staff']) || ($journeyForm->status==='demo_completed' && $application->status==='approved' && !$application->issuedDocument))<a class="btn btn-outline" href="{{ route('identity-workflow.administration.show',$journeyForm) }}">المراجعة الإدارية والاعتماد النهائي أو التصحيح قبل الإصدار</a>@endif @endcan
<h3>الإصدار والتسليم</h3>
@if($application->issuedDocument)<p>الوثيقة الداخلية: <bdi>{{ $application->issuedDocument->document_number }}</bdi> — التسليم: {{ $application->issuedDocument->delivery?->status }}</p>@can('deliverDocument',$application)<a class="btn btn-outline" href="{{ route('employee.documents.show',$application->issuedDocument) }}">تجهيز الوثيقة وتوثيق الاستلام</a>@endcan
@elseif($application->status==='approved')@can('issueDocument',$application)<a class="btn btn-primary" href="{{ route('employee.issuance.review',$application->id) }}">فحص الجاهزية وإصدار الوثيقة</a>@endcan @else<p>الإصدار متاح بعد الاعتماد النهائي فقط.</p>@endif
<h3>سجل أدلة الزيارة</h3><ul>@foreach($journeyForm->visitEvidence()->get() as $historyEvidence)<li>{{ $historyEvidence->created_at->format('Y-m-d H:i') }} — نسخة {{ $historyEvidence->form_revision }} — {{ $historyEvidence->kind==='card_photo'?'صورة البطاقة':'نتيجة الدم' }} — {{ $historyEvidence->status }}</li>@endforeach</ul>
<h3>الخط الزمني للمعاملة</h3><ol>@foreach(app(\App\Services\Tracking\ApplicationTimeline::class)->rows($application) as $journeyEntry)<li>{{ $journeyEntry->occurred_at }} — {{ \App\Services\Tracking\ApplicationTimeline::LABELS[$journeyEntry->action] ?? 'تحديث مسجل' }}@if($journeyEntry->public_reason)<p>{{ $journeyEntry->public_reason }}</p>@endif</li>@endforeach</ol>
</section>
@once @push('scripts')<script src="{{ asset('js/identity-card-camera.js') }}" defer></script>@endpush @endonce
@endif
