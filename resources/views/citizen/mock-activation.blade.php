@extends('layouts.public')
@section('title','استكمال تفعيل حسابي')
@push('scripts')<script src="{{ asset('js/linked-registration.js') }}?v={{ filemtime(public_path('js/linked-registration.js')) }}" defer></script>@endpush
@section('content')
<section class="section" dir="rtl"><div class="container"><article class="card" style="max-width:850px;margin:auto;padding:24px"><h1>استكمال تفعيل حسابي</h1>
<p class="alert alert-info">بيئة تجريبية: الحيوية ومطابقة الوجه نتائج محددة ببيانات اختبار خيالية، وليست تحققًا بيومتريًا أو حكوميًا حقيقيًا.</p>@include('operations.messages')
<h2>{{ $citizen->accountStatusLabel() }}</h2><p>معرّف الحساب الرقمي الداخلي: <bdi>{{ $citizen->digital_account_id }}</bdi> — ليس رقمًا وطنيًا.</p>
@if($activationRequest)<p>رقم متابعة طلب المراجعة: {{ $activationRequest->id }} — {{ $activationRequest->status }}</p><p>{{ $activationRequest->review_reason }}</p>@endif
@if($citizen->hasActivatedIdentity())<p class="alert alert-success">تم تفعيل الحساب في بيئة المحاكاة بنجاح.</p><a class="btn btn-primary" href="{{ route('citizen.dashboard') }}">الانتقال إلى لوحتي</a>
@elseif($citizen->registration_status==='initial_account')<p>تم ربط القيد المدني المحاكي. يبقى الحساب أوليًا لإصدار البطاقة لأول مرة؛ لا يُنشأ رقم وطني تلقائيًا.</p><a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">إصدار البطاقة لأول مرة</a>
@elseif($citizen->registration_status==='under_review')<p>طلبك قيد المراجعة. ستجد القرار أو طلب الاستكمال في إشعاراتك.</p>
@elseif($citizen->identity_path==='has_identity')
<ol><li>التقاط صورة الوجه</li><li>محاكاة الحيوية</li><li>محاكاة المطابقة مع المرجع</li><li>التفعيل أو الإحالة للمراجعة</li></ol>
<p>أظهر الوجه والرأس والكتفين كاملين، واجه الكاميرا مباشرة، واختر إضاءة وخلفية واضحتين. تجنب النظارات الشمسية وحجب الملامح أو تعديل الصورة. الفحص التقني يرفض الملفات غير الصالحة، ولا يثبت حيوية الوجه فعليًا.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('citizen.account.mock-face') }}">@csrf<input type="hidden" name="operation_token" value="{{ \Illuminate\Support\Str::uuid() }}">
<button class="btn btn-outline" type="button" data-live-start>فتح الكاميرا</button><video data-live-video autoplay playsinline hidden style="width:100%;max-width:400px"></video><button type="button" class="btn btn-outline" data-live-capture hidden>التقاط الصورة</button><p data-live-message role="status"></p>
<label for="mock-selfie">صورة مباشرة للوجه (JPG أو PNG، حتى 2 ميجابايت، 300 × 300 على الأقل)</label><input class="form-control" type="file" id="mock-selfie" name="selfie" accept="image/jpeg,image/png" capture="user" required>@error('selfie')<p class="text-danger">{{ $message }}</p>@enderror
<label><input type="checkbox" name="camera_acknowledged" value="1" required> التقطت هذه الصورة بنفسي الآن وأفهم أن النتيجة محاكاة.</label>@error('camera_acknowledged')<p class="text-danger">{{ $message }}</p>@enderror<button class="btn btn-primary">تنفيذ محاكاة التحقق</button></form>
@else<p>يلزم استكمال بيانات القيد المدني من خلال طلب المراجعة. لا تتاح الخدمات المقيدة حتى اعتماد المسار.</p>
@if(in_array($activationRequest?->status,['requires_correction','rejected'],true))
<form method="POST" action="{{ route('citizen.account.mock-correction') }}">@csrf
@foreach(['name'=>'الاسم الرباعي','birth_date'=>'تاريخ الميلاد','birth_place'=>'مكان الميلاد','father_name'=>'الاسم الرباعي للأب','mother_name'=>'الاسم الرباعي للأم','father_national_id'=>'رقم الأب الوطني (اختياري)','mother_national_id'=>'رقم الأم الوطني (اختياري)','family_reference'=>'رقم الأسرة (اختياري)','additional_information'=>'معلومات إضافية (اختياري)'] as $field=>$label)<div class="form-group"><label for="correction-{{ $field }}">{{ $label }}</label><input class="form-control" id="correction-{{ $field }}" name="{{ $field }}" type="{{ $field==='birth_date'?'date':'text' }}" value="{{ old($field,data_get($activationRequest->evidence,'registration.'.$field)) }}" @if(in_array($field,['name','birth_date','birth_place','father_name','mother_name'])) required @endif>@error($field)<p class="text-danger">{{ $message }}</p>@enderror</div>@endforeach
<button class="btn btn-primary">إرسال الاستكمال للمراجعة</button></form>@endif
@endif
<p><a href="{{ route('citizen.notifications.index') }}">إشعاراتي</a></p></article></div></section>
@endsection
