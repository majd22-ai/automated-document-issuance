@extends('layouts.public')
@section('title', 'تفعيل حسابي إلكترونيًا')
@push('scripts')<script src="{{ asset('js/remote-activation.js') }}?v={{ filemtime(public_path('js/remote-activation.js')) }}" defer></script>@endpush
@section('content')
<section class="section"><div class="container"><article class="card" style="padding:clamp(18px,4vw,36px);max-width:900px;margin:auto">
<h1>تفعيل حسابي إلكترونيًا</h1><p>التحقق من الرقم الوطني والجوال ← وثائق الهوية والسيلفي ← مراجعة الموظف واعتماد الحساب. لا يُشترط الحضور الشخصي لهذا المسار.</p>
@include('operations.messages')
@if($citizen->hasActivatedIdentity())
<p class="alert alert-success">تم تفعيل حسابك إلى منصة الإصدار الآلي بنجاح.</p>
@elseif($citizen->registration_status === 'initial_account' || $citizen->identity_path === 'no_identity')
<p>حسابك أولي دون رقم وطني معتمد؛ يستكمل مسار إصدار البطاقة أولًا. لا تُعامل شهادة الميلاد كرقم وطني ولا يتجاوز التفعيل الإلكتروني تحقق الإصدار الرسمي.</p><a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">إصدار البطاقة لأول مرة</a>
@else
@php($remote = $activationRequest?->workflow === 'remote' ? $activationRequest : null)
@if($remote?->review_reason)<div class="alert alert-info"><strong>{{ $remote->status === 'rejected' ? 'رُفض الطلب' : 'المطلوب استكماله' }}</strong><p>{{ $remote->review_reason }}</p></div>@endif
@if($remote?->status === 'pending')
<h2>طلبك بانتظار مراجعة الموظف</h2><p>مرجع داخلي: {{ $remote->id }} — {{ $remote->submitted_at?->format('Y-m-d H:i') }}</p><p>سيصلك إشعار بالموافقة أو الرفض أو طلب الاستكمال. لا يعني نجاح رمز الجوال اعتماد الهوية.</p>
@else
<h2>1. التحقق من الرقم الوطني والجوال</h2>
@if(!$remote?->otp_verified_at || !$remote?->visit_expires_at?->isFuture())
<button class="btn btn-primary" type="button" data-open-dialog="remote-identity-dialog">إدخال الرقم الوطني والجوال / إعادة إرسال الرمز</button>
<dialog id="remote-identity-dialog" @if($errors->hasAny(['national_id','phone','phone_change_confirmed'])) data-auto-open @endif aria-labelledby="remote-identity-title" style="max-width:min(92vw,520px);padding:24px;border-radius:16px">
<form method="dialog"><button class="btn btn-outline" aria-label="إغلاق">×</button></form><h2 id="remote-identity-title">الرقم الوطني والجوال</h2>
<form method="POST" action="{{ route('citizen.account.remote.code') }}">@csrf
<div class="form-group"><label for="remote-national">الرقم الوطني</label><input class="form-control" id="remote-national" name="national_id" value="{{ old('national_id') }}" required inputmode="numeric">@error('national_id')<p class="text-danger">{{ $message }}</p>@enderror</div>
<div class="form-group"><label for="remote-phone">رقم الجوال</label><input class="form-control" id="remote-phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" placeholder="7xxxxxxxx أو +9677xxxxxxxx">@error('phone')<p class="text-danger">{{ $message }}</p>@enderror</div>
<label><input type="checkbox" name="phone_change_confirmed" value="1" required> أؤكد أن هذا جوالي؛ سيُحدّث رقم حسابي عند اعتماد الموظف إذا كان مختلفًا، مع تسجيل التغيير.</label>@error('phone_change_confirmed')<p class="text-danger">{{ $message }}</p>@enderror
<button class="btn btn-primary">طلب رمز التحقق</button></form></dialog>
@if($remote?->otp_sent_at)
<form method="POST" action="{{ route('citizen.account.remote.verify') }}" class="card" style="padding:20px;margin-block:16px">@csrf
<p>الرمز صالح ثلاث دقائق من وقت الإرسال: {{ $remote->otp_sent_at->format('H:i:s') }}.</p><label for="remote-otp">رمز المصادقة</label><input id="remote-otp" name="otp" class="form-control" required inputmode="numeric" maxlength="6" autocomplete="one-time-code">@error('otp')<p class="text-danger">{{ $message }}</p>@enderror<button class="btn btn-primary">تحقق وانتقل إلى الوثائق</button></form>
@endif
@else
<p class="alert alert-success">نجح التحقق من الجوال. أكمل الخطوة الثانية.</p>
<h2>2. وثائق إثبات الهوية والسيلفي</h2>
<p>استخدم البطاقة الشخصية أساسًا. استخدم شهادة الميلاد فقط عند عدم توفر البطاقة؛ ستخضع لمراجعة إضافية. الصور خاصة وتظهر لك وللموظف المخوّل فقط.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('citizen.account.remote.submit') }}" data-remote-evidence>@csrf
<label>نوع الوثيقة<select name="document_kind" class="form-control" data-document-kind><option value="identity_card" @selected(old('document_kind')==='identity_card')>البطاقة الشخصية — الخيار الأساسي</option><option value="birth_certificate" @selected(old('document_kind')==='birth_certificate')>شهادة الميلاد — عند عدم توفر البطاقة فقط</option></select></label>
<label data-birth-only hidden><input name="no_physical_card" type="checkbox" value="1"> أقر بعدم توفر البطاقة الشخصية لدي وأطلب مراجعة شهادة الميلاد بدلًا عنها.</label>@error('no_physical_card')<p class="text-danger">{{ $message }}</p>@enderror
@foreach(['front'=>'الوجه الأمامي للبطاقة أو شهادة الميلاد كاملة','back'=>'الوجه الخلفي للبطاقة','selfie'=>'سيلفي مباشر وأنت تحمل الوثيقة بجانب وجهك'] as $field=>$label)
<div class="form-group" @if($field==='back') data-card-back @endif><label for="activation-{{ $field }}">{{ $label }}</label><input type="file" id="activation-{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png" capture="{{ $field === 'selfie' ? 'user' : 'environment' }}" class="form-control" required @if($field==='selfie') data-selfie-file @endif>@error($field)<p class="text-danger">{{ $message }}</p>@enderror</div>
@endforeach
<p>JPG أو PNG، بحد أقصى 5 ميجابايت لكل صورة وأبعاد لا تقل عن 300 × 300. يجب أن تظهر جميع بيانات الوثيقة بوضوح.</p>
<button class="btn btn-outline" type="button" data-start-selfie>فتح الكاميرا لالتقاط السيلفي</button><video data-selfie-video autoplay playsinline hidden style="width:100%;max-width:420px"></video><button class="btn btn-outline" type="button" data-capture-selfie hidden>التقاط الصورة</button><p data-camera-status role="status"></p>
<label><input type="checkbox" name="selfie_capture_acknowledged" value="1" required> التقطت السيلفي بنفسي الآن وأنا أحمل الوثيقة. لا يدعي النظام التحقق الآلي من الحيوية أو مطابقة الوجه.</label>
<label><input type="checkbox" name="declaration" value="1" required> أقر بصحة البيانات والوثائق المرفوعة وأوافق على مراجعتها من الموظف المختص.</label>
@error('selfie_capture_acknowledged')<p class="text-danger">{{ $message }}</p>@enderror @error('declaration')<p class="text-danger">{{ $message }}</p>@enderror
<button class="btn btn-primary">إرسال طلب التفعيل للمراجعة</button></form>
@endif
@endif
@if(\App\Services\Registration\LocalSms::enabled() && session('local_activation_code'))
<dialog id="remote-code-preview" data-auto-open aria-labelledby="local-code-title" style="max-width:min(92vw,480px);padding:24px;border-radius:16px"><form method="dialog"><button class="btn btn-outline" aria-label="إغلاق">×</button></form><h2 id="local-code-title">محاكاة رسالة الجوال</h2><p>رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف.</p><p>الرقم الذي أدخلته: <bdi>{{ $remote?->visit_phone }}</bdi></p><input class="form-control" readonly value="{{ session('local_activation_code') }}" data-local-code><button class="btn btn-outline" type="button" data-copy-code>نسخ الرمز</button></dialog>
@endif
@endif
<p><a href="{{ route('citizen.notifications.index') }}">إشعاراتي</a> · <a href="{{ route('citizen.dashboard') }}">لوحتي</a></p>
</article></div></section>
@endsection
