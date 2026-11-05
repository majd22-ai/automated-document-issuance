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
@if($systemIndex!==2 || $p->card_number)<tr><td>{{ $p->name }}</td><td>@switch($systemIndex) @case(2){{ $p->card_number }}@break @case(3){{ $p->birth_number }} — {{ $p->birth_date }} — {{ $p->birth_place }}@break @case(4){{ $p->family_reference }} — {{ $p->family_role }}@break @case(5){{ $p->face_scenario }} — الحيوية: {{ $p->liveness_pass?'نجاح تجريبي':'فشل تجريبي' }} — المرجع: {{ $p->reference_photo?'رسم اختبار':'لا يوجد' }}@break @default{{ $p->national_id ?? 'دون رقم وطني' }} — {{ $p->birth_date }}@endswitch</td><td>{{ $p->complete?'مكتمل تجريبيًا':'يحتاج مراجعة' }}</td></tr>@endif
@endforeach</tbody></table><p>الأب: أب محاكاة أسرة تجريبية. الأم: أم محاكاة أسرة تجريبية. جميع الأسماء والروابط اصطناعية.</p>@endif
</dialog>@endforeach
{{ $people->links() }}</div></section>@endsection
