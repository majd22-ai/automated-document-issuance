@extends('layouts.public')
@section('title','أنظمة المحاكاة المدنية')
@push('scripts')<script src="{{ asset('js/linked-registration.js') }}" defer></script>@endpush
@section('content')<section class="section"><div class="container"><h1>أنظمة المحاكاة المدنية المترابطة</h1><p class="alert alert-info">سجلات خيالية بالكامل لمشروع التخرج. لا يوجد اتصال بأنظمة حكومية أو خدمات رسائل خارجية. الرسوم المرجعية ليست صور مواطنين.</p>
@foreach(['السجل المدني','الأحوال المدنية','البطاقة الشخصية','سجل المواليد','الأسرة والقيد العائلي','الهوية ومطابقة الوجه','رموز التحقق والإشعارات'] as $system)
<button type="button" class="btn btn-outline" data-dialog-open="mock-system-{{ $loop->index }}">{{ $system }}</button><dialog id="mock-system-{{ $loop->index }}" style="width:min(92vw,900px);max-height:85vh;overflow:auto" aria-label="{{ $system }}"><form method="dialog"><button aria-label="إغلاق" class="btn btn-outline">×</button></form><h2>{{ $system }} — محاكاة محلية</h2>
@if($loop->index===6)<p>رموز التحقق مخزنة كتجزئة مؤقتة في registration_attempts، ولا تعرض هنا. الإشعارات تستخدم صندوق النظام الحالي؛ لا ترسل SMS أو بريدًا حقيقيًا.</p>
@else
@php($systemIndex=$loop->index)
<table class="table"><thead><tr><th>الاسم الخيالي</th><th>{{ $systemIndex===2?'رقم البطاقة':($systemIndex===3?'رقم سجل الميلاد':($systemIndex===4?'سجل الأسرة':'بيانات النظام')) }}</th><th>حالة السجل</th></tr></thead><tbody>
@foreach($people as $p)
@if($systemIndex!==2 || $p->card_number)<tr><td>{{ $p->name }}
@if($p->display_photo)
<button type="button" class="btn btn-outline" data-dialog-open="registry-portrait-{{ $systemIndex }}-{{ $p->id }}">عرض الصورة</button>
<dialog id="registry-portrait-{{ $systemIndex }}-{{ $p->id }}" aria-label="صورة {{ $p->name }}" style="width:min(92vw,680px)"><form method="dialog"><button class="btn btn-outline" aria-label="إغلاق الصورة">إغلاق</button></form><h3>{{ $p->name }}</h3>
@if(str_contains($p->display_photo,'illustration'))<p>هذا رسم الاختبار السابق. لم تُولد الصورة الشخصية الجديدة بعد.</p>@else<p>صورة شخصية افتراضية للاختبار.</p>@endif
<img src="{{ route('admin.mock-systems.portrait', $p->id) }}" alt="الصورة التجريبية لـ {{ $p->name }}" loading="lazy" width="600" height="600" style="max-width:100%;height:auto"><p>PNG أو JPG — 600 × 600 على الأقل — بحد أقصى 2 ميجابايت.</p><a class="btn btn-outline" href="{{ route('admin.mock-systems.portrait', $p->id) }}" download>تنزيل الصورة</a></dialog>
@else<span>لا توجد صورة مرجعية لهذا السيناريو</span>@endif
</td><td>@switch($systemIndex) @case(2){{ $p->card_number }}@break @case(3){{ $p->birth_number }} — {{ $p->birth_date }} — {{ $p->birth_place }}@break @case(4){{ $p->family_reference }} — {{ $p->family_role }}@break @case(5){{ $p->face_scenario }} — الحيوية: {{ $p->liveness_pass?'نجاح تجريبي':'فشل تجريبي' }} — المرجع: {{ $p->reference_photo?'رسم اختبار':'لا يوجد' }}@break @default{{ $p->national_id ?? 'دون رقم وطني' }} — {{ $p->birth_date }}@endswitch</td><td>{{ $p->complete?'مكتمل تجريبيًا':'يحتاج مراجعة' }}</td></tr>@endif
@endforeach</tbody></table><p>الأب: أحمد عبد الله ناصر العمري. الأم: سعاد علي صالح الحداد. جميع الأسماء والروابط اصطناعية.</p>@endif
</dialog>@endforeach
{{ $people->links() }}</div></section>@endsection
