@extends('layouts.public')
@section('title', 'المواعيد والفحوصات والرسوم')
@section('content')
@php($prefix = auth()->user()->role === 'employee' ? 'employee' : 'citizen')
<section class="section"><div class="container"><div class="ops-page-head"><h1>المواعيد والفحوصات والرسوم</h1><p class="ops-help">تابع الموعد والفحص والالتزام المالي لهذا الطلب. تظهر إجراءات التعديل وفق صلاحية الحساب وحالة الطلب.</p></div><h2>{{ $service->name }}</h2><p>الطلب: {{ $application->reference }} — {{ $application->statusLabel() }}</p>
@include('operations.messages')
@include('components.requirements-result', ['requirementsResult'=>$report->toArray()])
<section class="card ops-card mb-3" style="padding:1.5rem"><h2>المواعيد المتاحة</h2><p>الأوقات بتوقيت {{ config('operations.timezone') }}. التوفر يعاد التحقق منه عند تأكيد الحجز.</p>
@can('operate', $application)
<form method="POST" action="{{ route($prefix.'.applications.appointments.store',$application->id) }}">@csrf
<label for="request-slot" style="display:block;margin-block:1rem .4rem">اختر الفترة ومركز الخدمة</label><select class="form-control" id="request-slot" name="slot_id" required><option value="">اختر موعدًا</option>@foreach($slots as $slot)<option value="{{ $slot->id }}">{{ $slot->center }} — {{ $slot->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — المتبقي {{ $slot->capacity-$slot->bookings_count }}</option>@endforeach</select><div style="margin-block-start:1rem"><button class="btn btn-primary" type="submit">حجز الموعد</button></div></form>
@endcan
@if($slots->isEmpty())<p>لا توجد فترات متاحة حاليًا. راجع مركز الخدمة.</p>@endif {{ $slots->links() }}
<h3>سجل مواعيد الطلب</h3>
@forelse($appointments as $appointment)
<article class="card ops-card mb-3" style="padding:1rem"><p>{{ $appointment->slot->center }} — {{ $appointment->slot->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — {{ $appointment->statusLabel() }}</p>
@can('operate',$application)
@if(in_array($appointment->status,['booked','confirmed']))
<form method="POST" action="{{ route($prefix.'.appointments.update',$appointment) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="cancel"><div style="margin-block-start:1rem"><button class="btn btn-outline">إلغاء الموعد</button></div></form>
<form method="POST" action="{{ route($prefix.'.appointments.update',$appointment) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reschedule"><label for="reschedule-{{ $appointment->id }}" style="display:block;margin-block:1rem .4rem">إعادة الجدولة</label><select class="form-control" name="slot_id" id="reschedule-{{ $appointment->id }}" required><option value="">اختر فترة بديلة</option>@foreach($slots as $slot)<option value="{{ $slot->id }}">{{ $slot->center }} — {{ $slot->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }}</option>@endforeach</select><div style="margin-block-start:1rem"><button class="btn btn-outline">تأكيد إعادة الجدولة</button></div></form>
@if($prefix==='employee')<form method="POST" action="{{ route('employee.appointments.update',$appointment) }}">@csrf @method('PATCH')<label for="state-{{ $appointment->id }}" style="display:block;margin-block:1rem .4rem">تحديث حالة الحضور</label><select class="form-control" name="action" id="state-{{ $appointment->id }}"><option value="confirmed">مؤكد</option><option value="completed">مكتمل</option><option value="no_show">لم يحضر</option></select><div style="margin-block-start:1rem"><button class="btn btn-outline">حفظ حالة الموعد</button></div></form>@endif
@endif @endcan</article>
@empty<p>لم يُحجز موعد لهذا الطلب.</p>@endforelse
</section>
<section class="card ops-card mb-3" style="padding:1.5rem"><h2>الفحوصات الطبية</h2>
@if($service->verification_rules['medical']['required'] ?? false)
@forelse($examinations as $exam)<article class="card ops-card mb-3" style="padding:1rem"><p>{{ $loop->first ? 'أحدث نتيجة: ' : 'نتيجة سابقة: ' }}{{ $exam->resultLabel() }} — الفحص: {{ $exam->examined_at?->format('Y-m-d') ?? 'لم يجر بعد' }} — الصلاحية: {{ $exam->expires_at?->format('Y-m-d') ?? 'غير مسجلة' }}</p>@if($prefix==='employee')<p>مرجع الإدخال اليدوي: {{ $exam->source_reference }}</p><p>ملاحظات طبية داخلية: {{ $exam->private_notes ?? 'لا توجد' }}</p>@endif</article>@empty<p>لا توجد نتيجة فحص مسجلة. غياب النتيجة لا يعني النجاح.</p>@endforelse
@can('recordOperations',$application)
<form method="POST" action="{{ route('employee.applications.examinations.store',$application->id) }}">@csrf<input type="hidden" name="expected_id" value="{{ $examinations->first()?->id ?? 0 }}"><input type="hidden" name="expected_rules_version" value="{{ $service->rules_version }}">
<p>إدخال يدوي لموظف مخول بناءً على مستند صادر من الجهة المختصة؛ لا يوجد تحقق طبي خارجي آلي.</p>
<h3>تسجيل نتيجة الفحص</h3><label for="exam-result" style="display:block;margin-block:1rem .4rem">النتيجة</label><select class="form-control" name="result" id="exam-result" required><option value="pending">قيد الانتظار</option><option value="passed">ناجح</option><option value="failed">غير ناجح</option><option value="retest">يحتاج إلى إعادة فحص</option></select>
<label for="exam-date" style="display:block;margin-block:1rem .4rem">تاريخ الفحص</label><input class="form-control" type="date" name="examined_at" id="exam-date">
<label for="exam-expiry" style="display:block;margin-block:1rem .4rem">تاريخ الانتهاء الموثق (عند اشتراطه)</label><input class="form-control" type="date" name="expires_at" id="exam-expiry">
<label for="exam-source" style="display:block;margin-block:1rem .4rem">مرجع النتيجة من الجهة الطبية</label><input class="form-control" name="source_reference" id="exam-source" required maxlength="255">
<label for="exam-appointment" style="display:block;margin-block:1rem .4rem">الموعد المرتبط (اختياري)</label><select class="form-control" name="citizen_appointment_id" id="exam-appointment"><option value="">دون ربط بموعد</option>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}">{{ $appointment->slot->center }} — {{ $appointment->statusLabel() }}</option>@endforeach</select>
<label for="exam-notes" style="display:block;margin-block:1rem .4rem">ملاحظات داخلية لا تظهر للمواطن</label><textarea class="form-control" name="private_notes" id="exam-notes" maxlength="2000"></textarea><div style="margin-block-start:1rem"><button class="btn btn-primary">تسجيل نسخة جديدة من النتيجة</button></div></form>
@endcan
@else<p>لا تشترط قواعد هذه الخدمة فحصًا طبيًا.</p>@endif
</section>
<section class="card ops-card mb-3" style="padding:1.5rem"><h2>رسوم الطلب والسداد</h2>
@if($charge)
<p>المستحق وقت إنشاء الالتزام: {{ $charge->amount }} {{ $charge->currency }} — {{ $charge->statusLabel() }}</p>
<p>المتبقي: {{ $charge->status==='paid' || $charge->status==='not_required' ? '0.00' : $charge->amount }} {{ $charge->currency }}</p>
@if($charge->payment)<p>سداد يدوي: {{ $charge->payment->amount }} {{ $charge->payment->currency }} — {{ $charge->payment->paid_at->format('Y-m-d') }} — مرجع الإيصال {{ $charge->payment->receipt_reference }}</p><p>الطريقة: {{ $charge->payment->method === 'cash' ? 'نقدي بإيصال موثق' : 'تحويل بنكي مسجل يدويًا' }}. لا توجد بوابة دفع إلكترونية متكاملة.</p>@endif
@else<p>لم ينشأ التزام مالي بعد. الرسم المحفوظ مع الطلب: {{ $application->service_fee === null ? 'غير معروف؛ لا يُفترض صفرًا' : $application->service_fee.' '.$application->service_currency }}</p>
@can('recordOperations',$application)<form method="POST" action="{{ route('employee.applications.charges.store',$application->id) }}">@csrf<div style="margin-block-start:1rem"><button class="btn btn-outline">إنشاء الرسم من بيانات الطلب المحفوظة</button></div></form>@endcan
@endif
@can('recordOperations',$application)
@if(!$charge || $charge->status==='due')
<form method="POST" action="{{ route('employee.applications.payments.store',$application->id) }}">@csrf
<p>يجب التحقق من الإيصال الأصلي قبل التسجيل. لا تعتمد العملية على تصريح المواطن بأنه دفع.</p>
<h3>بيانات الإيصال والسداد</h3><label for="payment-amount" style="display:block;margin-block:1rem .4rem">المبلغ الكامل</label><input class="form-control" name="amount" id="payment-amount" value="{{ $charge?->amount ?? $application->service_fee }}" inputmode="decimal" required>
<label for="payment-currency" style="display:block;margin-block:1rem .4rem">العملة</label><input class="form-control" name="currency" id="payment-currency" value="{{ $charge?->currency ?? $application->service_currency }}" maxlength="20" required>
<label for="payment-reference" style="display:block;margin-block:1rem .4rem">مرجع الإيصال الفريد (حروف إنجليزية وأرقام)</label><input class="form-control" name="receipt_reference" id="payment-reference" maxlength="150" required>
<label for="payment-date" style="display:block;margin-block:1rem .4rem">تاريخ السداد</label><input class="form-control" name="paid_at" id="payment-date" type="date" required>
<label for="payment-method" style="display:block;margin-block:1rem .4rem">الطريقة</label><select class="form-control" name="method" id="payment-method"><option value="cash">نقدي بإيصال موثق</option><option value="bank_transfer">تحويل بنكي — تحقق يدوي</option></select><div style="margin-block-start:1rem"><button class="btn btn-primary">تسجيل السداد اليدوي</button></div></form>
@endif @endcan
</section>
<a href="{{ $prefix==='citizen' ? route('citizen.applications.show',$application->id) : route('employee.applications.review',$application->id) }}">العودة إلى تفاصيل الطلب</a>
</div></section>
@endsection
