@extends('layouts.auth')
@section('title','إنشاء حساب مواطن')
@push('styles')<link rel="stylesheet" href="{{ asset('css/registration.css') }}?v={{ filemtime(public_path('css/registration.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/linked-registration.js') }}?v={{ filemtime(public_path('js/linked-registration.js')) }}" defer></script>@endpush
@section('content')
<section class="auth-wrapper registration-wrapper linked-registration" dir="rtl"><article class="auth-card registration-card">
<header class="auth-logo registration-heading"><img class="auth-platform-logo" src="{{ asset('assets/images/logos/unified-issuance-logo.webp') }}" alt="شعار مركز الإصدار الآلي الموحد" width="120" height="120"><h1>إنشاء حساب لأول مرة</h1><p>خطوات بسيطة لإنشاء حسابك والوصول إلى خدمات المنصة.</p></header>
<ol class="registration-steps" aria-label="مراحل التسجيل">@foreach([1=>'بيانات المواطن',2=>'التحقق من الهاتف',3=>'كلمة المرور'] as $number=>$label)<li class="{{ $step===$number?'current':($step>$number?'done':'') }}" @if($step===$number) aria-current="step" @endif><span>{{ $number }}</span>{{ $label }}</li>@endforeach</ol>
<p class="registration-notice linked-demo-notice">نسخة تجريبية أكاديمية: التحقق والسجلات محاكاة محلية، ولا تُرسل رسائل أو طلبات إلى جهات حكومية.</p>
@include('operations.messages')
@if($step===1)
<form method="POST" action="{{ route('register.post') }}" class="auth-form registration-form" enctype="multipart/form-data" data-linked-register>@csrf
<fieldset class="linked-path"><legend>اختر نوع التسجيل</legend><div class="linked-path-options"><label><input type="radio" name="identity_path" value="has_identity" required @checked(old('identity_path')==='has_identity')><span><strong>لدي بطاقة شخصية</strong><small>التسجيل باستخدام الرقم الوطني.</small></span></label><label><input type="radio" name="identity_path" value="no_identity" required @checked(old('identity_path')==='no_identity')><span><strong>لا أمتلك بطاقة شخصية</strong><small>التسجيل ببيانات الميلاد والأسرة.</small></span></label></div>@error('identity_path')<p class="text-danger">{{ $message }}</p>@enderror</fieldset>
<div class="registration-grid">
@foreach(['name'=>'الاسم الكامل / الرباعي','national_id'=>'الرقم الوطني — رقم البطاقة الشخصية','birth_date'=>'تاريخ الميلاد','birth_place'=>'مكان الميلاد','father_name'=>'الاسم الرباعي للأب','mother_name'=>'الاسم الرباعي للأم','father_national_id'=>'الرقم الوطني للأب (اختياري)','mother_national_id'=>'الرقم الوطني للأم (اختياري)','email'=>'البريد الإلكتروني','phone'=>'رقم الجوال اليمني (9 أرقام دون +967)'] as $field=>$label)
<div class="form-group" @if($field==='national_id') data-card-fields @elseif(!in_array($field,['name','email','phone'])) data-family-fields @endif><label for="linked-{{ $field==='birth_date'?'birth_day':$field }}">{{ $label }}</label>@if($field==='birth_date')
<div class="linked-birth-date" role="group" aria-label="????? ???????">
@foreach(['birth_day'=>'?????','birth_month'=>'?????','birth_year'=>'?????'] as $part=>$partLabel)
<select class="form-control" name="{{ $part }}" id="linked-{{ $part }}" aria-label="{{ $partLabel }}" required>
<option value="">{{ $partLabel }}</option>
@foreach(($part==='birth_year'?range((int)date('Y'),1900):range(1,$part==='birth_month'?12:31)) as $value)
<option value="{{ $value }}" @selected((int)old($part) === $value)>{{ $value }}</option>
@endforeach
</select>
@endforeach
</div>
@else
<input class="form-control" id="linked-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" type="{{ $field==='phone'?'tel':($field==='email'?'email':($field==='birth_date'?'date':'text')) }}" @if(in_array($field,['name','email','phone','national_id','birth_date','birth_place','father_name','mother_name'])) required @endif maxlength="{{ $field==='phone'?9:($field==='additional_information'?1000:150) }}" @if($field==='phone') minlength="9" inputmode="numeric" pattern="7[0-9]{8}" autocomplete="tel-national" dir="ltr" @endif>@endif
@error($field)<p class="text-danger">{{ $message }}</p>@enderror</div>
@endforeach
<div class="form-group" data-family-fields><label for="linked-birth">صورة شهادة الميلاد (اختيارية)</label><input id="linked-birth" name="birth_certificate" type="file" accept="image/jpeg,image/png" class="form-control linked-birth-upload">@error('birth_certificate')<p class="text-danger">{{ $message }}</p>@enderror</div>
</div>
<label class="registration-terms"><input type="checkbox" name="terms" value="1" required> أوافق على شروط الاستخدام وإجراءات المحاكاة.</label>@error('terms')<p class="text-danger">{{ $message }}</p>@enderror
<div class="registration-actions"><button class="btn btn-primary linked-submit">التحقق من السجل والمتابعة</button></div></form>
@elseif($step===2)
<h2>مصادقة رقم الهاتف</h2><p>الرمز التجريبي صالح ثلاث دقائق ويستخدم مرة واحدة. لم تُرسل رسالة إلى شبكة الهاتف.</p><p>الهاتف: <bdi>{{ substr($payload['phone'],0,6) }}••••{{ substr($payload['phone'],-2) }}</bdi></p>
<form class="auth-form registration-form linked-verification" method="POST" action="{{ route('register.verify.post') }}">@csrf<label for="linked-code">رمز التحقق</label><input class="form-control otp-input" id="linked-code" name="code" required inputmode="numeric" maxlength="6" autocomplete="one-time-code" dir="ltr" placeholder="••••••">@error('code')<p class="text-danger">{{ $message }}</p>@enderror<button class="btn btn-primary linked-submit">تحقق وانتقل لكلمة المرور</button></form>
<form class="linked-resend" method="POST" action="{{ route('register.resend') }}">@csrf<button class="btn btn-outline">إعادة توليد الرمز بعد دقيقة</button></form>
@if(session('mock_registration_code'))
<button class="btn btn-outline" type="button" data-dialog-open="linked-otp-preview">عرض الرسالة التجريبية</button>
<dialog id="linked-otp-preview" data-dialog-auto aria-labelledby="linked-otp-title"><form method="dialog"><button aria-label="إغلاق" class="btn btn-outline">×</button></form><h2 id="linked-otp-title">محاكاة رسالة تحقق</h2><p>رمز تجريبي لم يُرسل إلى شبكة الهاتف.</p><p>{{ $payload['email'] }}</p><p><bdi>{{ $payload['phone'] }}</bdi></p><input readonly class="form-control" value="{{ \Illuminate\Support\Facades\Crypt::decryptString(session('mock_registration_code')) }}" data-copy-value><button type="button" class="btn btn-outline" data-copy-local>نسخ الرمز</button></dialog>
@endif
@else
<h2>إنشاء كلمة المرور</h2><p>8 أحرف على الأقل، تشمل الحروف والأرقام والرموز.</p><form class="auth-form registration-form linked-password" method="POST" action="{{ route('register.password') }}">@csrf
@if($payload['identity_path']==='no_identity')<div class="form-group"><label for="linked-username">اسم المستخدم <span aria-hidden="true">*</span></label><input id="linked-username" class="form-control" name="username" value="{{ old('username') }}" required minlength="4" maxlength="20" pattern="[A-Za-z][A-Za-z0-9_]{3,19}" autocomplete="username" dir="ltr" aria-describedby="linked-username-help"><small id="linked-username-help">اختر اسمًا من 4 إلى 20 خانة يبدأ بحرف إنجليزي، باستخدام الحروف الإنجليزية والأرقام والشرطة السفلية، مثل husam23. ستستخدمه مع كلمة المرور لتسجيل الدخول بدل الرقم الوطني.</small>@error('username')<p class="text-danger">{{ $message }}</p>@enderror</div>@endif
@foreach(['password'=>'كلمة المرور','password_confirmation'=>'تأكيد كلمة المرور'] as $field=>$label)<div class="form-group"><label for="linked-{{ $field }}">{{ $label }}</label><input id="linked-{{ $field }}" name="{{ $field }}" type="password" required autocomplete="new-password" class="form-control">@error($field)<p class="text-danger">{{ $message }}</p>@enderror</div>@endforeach
<button class="btn btn-primary linked-submit">إنشاء الحساب والانتقال لتسجيل الدخول</button></form>
@endif
<div class="auth-links linked-login-link"><span>لديك حساب بالفعل؟</span><a href="{{ route('login') }}">تسجيل الدخول</a></div>
</article></section>
@endsection
