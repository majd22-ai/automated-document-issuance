@extends('layouts.auth')
@section('title', 'إنشاء حساب جديد — بوابة خدمات السجل المدني')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/registration.css') }}?v={{ filemtime(public_path('css/registration.css')) }}">
<link rel="stylesheet" href="{{ asset('css/local-password-recovery.css') }}?v={{ filemtime(public_path('css/local-password-recovery.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/registration.js') }}?v={{ filemtime(public_path('js/registration.js')) }}" defer></script>
<script src="{{ asset('js/local-password-recovery.js') }}?v={{ filemtime(public_path('js/local-password-recovery.js')) }}" defer></script>
@endpush
@section('content')
<section class="auth-wrapper registration-wrapper" dir="rtl">
<div class="auth-card registration-card">
<header class="registration-heading"><h1>إنشاء حساب جديد — بوابة خدمات السجل المدني</h1><p>بياناتك آمنة. التحقق من الجوال خطوة مستقلة عن اعتماد هويتك المدنية.</p></header>
<ol class="registration-steps" aria-label="خطوات التسجيل">
@foreach ([1 => 'بيانات المستخدم', 2 => 'التحقق', 3 => 'انتهاء التسجيل'] as $number => $label)
<li class="{{ $step === $number ? 'current' : ($step > $number ? 'done' : '') }}" @if($step === $number) aria-current="step" @endif><span>{{ $number }}</span>{{ $label }}</li>
@endforeach
</ol>
@if($errors->any())<div class="alert alert-danger" role="alert">يرجى تصحيح الأخطاء الموضحة تحت الحقول.</div>@endif
@if(session('status'))<p class="alert alert-info" role="status">{{ session('status') }}</p>@endif
@if($step === 1)
@php($values = array_merge($form ?? [], session()->getOldInput()))
<form method="POST" action="{{ route('register.post') }}" class="auth-form registration-form" id="registration-form" novalidate data-captcha-check="{{ route('register.captcha.check') }}">
@csrf
<fieldset><legend>المعلومات الشخصية</legend>
<div class="registration-grid">
<div class="form-group"><label for="name">الاسم الكامل</label><input class="form-control" id="name" name="name" value="{{ $values['name'] ?? '' }}" required maxlength="100" autocomplete="name">@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="identity_path">حالة السجل المدني</label><select class="form-control" id="identity_path" name="identity_path">
<option value="has_identity" @selected(($values['identity_path'] ?? '') === 'has_identity')>لدي رقم هوية</option>
<option value="no_identity" @selected(($values['identity_path'] ?? '') === 'no_identity')>لا أملك رقم هوية — حساب أولي</option>
</select>@error('identity_path')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="national_id">رقم الهوية</label><input class="form-control" id="national_id" name="{{ ($values['identity_path'] ?? '') === 'no_identity' ? 'birth_certificate_number' : 'national_id' }}" placeholder="{{ ($values['identity_path'] ?? '') === 'no_identity' ? 'أدخل رقم شهادة الميلاد الخاص بك' : 'أدخل رقم الهوية الخاص بك' }}" value="{{ ($values['identity_path'] ?? '') === 'no_identity' ? ($values['birth_certificate_number'] ?? '') : ($values['national_id'] ?? '') }}" inputmode="numeric" pattern="[0-9]{5,20}" maxlength="20" required><button type="button" class="registration-link" id="no-identity">لا أملك رقم هوية</button>@error('national_id')<small class="field-error">{{ $message }}</small>@enderror @error('birth_certificate_number')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="phone">رقم الجوال اليمني</label><div class="phone-input" dir="ltr"><span>+967</span><input class="form-control" type="tel" id="phone" name="phone" value="{{ $values['phone'] ?? '' }}" pattern="7[0-9]{8}" maxlength="9" inputmode="numeric" required autocomplete="tel-national" aria-describedby="phone-help"></div><small id="phone-help">9 أرقام تبدأ بالرقم 7 دون رمز الدولة.</small>@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
</div></fieldset>
<fieldset><legend>معلومات الحساب</legend><div class="registration-grid">
<div class="form-group"><label for="email">البريد الإلكتروني</label><input class="form-control" type="email" id="email" name="email" value="{{ $values['email'] ?? '' }}" required maxlength="255" autocomplete="email" dir="ltr">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="username">اسم المستخدم</label><input class="form-control" id="username" name="username" value="{{ $values['username'] ?? '' }}" minlength="4" maxlength="30" pattern="[A-Za-z0-9_]+" required autocomplete="username" dir="ltr"><small>4 إلى 30 خانة: حروف إنجليزية وأرقام وشرطة سفلية. يتحقق الخادم من التوفر عند الإرسال.</small>@error('username')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="password">كلمة المرور</label><input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="255" required autocomplete="new-password" aria-describedby="password-help"><small id="password-help">يجب أن تتكون كلمة المرور من حروف وأرقام ورموز. يوصى بألا تقل عن 8 خانات.</small>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="form-group"><label for="password_confirmation">تأكيد كلمة المرور</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"><small class="field-error" id="password-match" role="status">{{ $errors->first('password_confirmation') }}</small></div>
</div></fieldset>
<div class="captcha-section"><label for="captcha">رمز التحقق في الصورة</label><div class="captcha-image"><img src="{{ route('register.captcha') }}" id="captcha-image" width="220" height="64" alt="صورة رمز التحقق؛ أدخل الحروف والأرقام الظاهرة"><button type="button" id="refresh-captcha" class="registration-link">تحديث الصورة</button></div><input class="form-control" id="captcha" name="captcha" required pattern="[A-Za-z2-9]{6}" maxlength="6" autocomplete="off" dir="ltr"><button type="button" class="registration-link" id="check-captcha">تحقق من الرمز</button><small id="captcha-status" role="status"></small><small>تحقق محلي أساسي من جهة الخادم؛ يلزم تعزيز الحماية قبل الإطلاق العام.</small>@error('captcha')<small class="field-error">{{ $message }}</small>@enderror</div>
<label class="registration-terms"><input type="checkbox" name="terms" id="terms" value="1" required>أقر بأنني اطلعت على <a href="{{ route('terms') }}" target="_blank" rel="noopener">شروط الاستخدام</a></label>@error('terms')<small class="field-error">{{ $message }}</small>@enderror
<p class="registration-notice">لا يكتمل التسجيل إلا بعد التحقق من الجوال. إذا لم تكن خدمة الرسائل متاحة سيظهر تنبيه واضح، ولن يُنشأ حساب.</p>
<p id="registration-feedback" class="field-error" role="status" aria-live="polite"></p><button type="submit" id="registration-next" class="btn btn-primary btn-block">التالي</button>
<noscript><p>فعّل JavaScript لاستخدام الخطوات والتحقق المحلي. يبقى التحقق الأمني من جهة الخادم.</p></noscript>
</form>
<dialog id="identity-dialog" aria-labelledby="identity-dialog-title"><form method="dialog" id="identity-dialog-form"><h2 id="identity-dialog-title">لا أملك رقم هوية</h2><p>إذا لم تكن تمتلك بطاقة شخصية أو رقم هوية، يمكنك استخدام رقم هاتفك اليمني لإنشاء حساب أولي يتيح لك تقديم طلب إصدار بطاقة شخصية لأول مرة فقط. ستحتاج إلى استكمال إجراءات التحقق من الهوية لدى السجل المدني قبل اعتماد هويتك وإتاحة الخدمات الأخرى.</p><p>أدخل رقم شهادة الميلاد ورقم الجوال في النموذج بعد الموافقة. رقم شهادة الميلاد لا يُعد رقم هوية وطنية.</p><small>إذا كان لديك سجل مدني سابق ورقم هوية، اختر «لدي رقم هوية» في النموذج.</small><div class="registration-actions"><button type="button" id="cancel-identity" class="btn btn-outline">إلغاء</button><button type="submit" class="btn btn-primary">موافق</button></div></form></dialog>
<div class="auth-links"><a href="{{ route('login') }}">لديك حساب؟ سجّل الدخول</a></div>
@elseif($step === 2)
@if(!empty($localCode))
<p class="alert alert-info" role="status">تجربة محلية: انسخ رمز التحقق من النافذة وأدخله أدناه. لم تُرسل رسالة فعلية.</p>
<dialog id="recovery-preview" class="recovery-preview" open aria-labelledby="registration-preview-title">
<button type="button" class="recovery-close" data-recovery-close aria-label="إغلاق نافذة رمز التحقق">×</button>
<h2 id="registration-preview-title">رمز التحقق المحلي</h2>
<p>البريد الإلكتروني المدخل</p><input class="form-control recovery-email" readonly dir="ltr" value="{{ $registrationEmail }}" aria-label="البريد الإلكتروني المدخل">
<p>هذا رمز تجريبي؛ لا يثبت ملكية الجوال أو البريد الإلكتروني.</p>
<label for="local-recovery-copy-code">رمز التحقق</label><input id="local-recovery-copy-code" class="form-control recovery-copy-code" readonly dir="ltr" value="{{ $localCode }}" autocomplete="off">
<button type="button" class="btn btn-secondary btn-block" data-recovery-copy>نسخ الرمز</button><p data-recovery-copy-status role="status" aria-live="polite"></p>
</dialog>
@else
<h2>التحقق من رقم الجوال</h2><p>أرسلنا رمز تحقق مكونًا من 6 أرقام إلى رقم الجوال الذي أدخلته. يرجى إدخال الرمز لإكمال التسجيل.</p><p dir="ltr" class="masked-phone">{{ $maskedPhone }}</p>
@endif
<form method="POST" action="{{ route('register.verify.post') }}" class="auth-form" data-registration-submit>@csrf<label for="code">رمز التحقق</label><input class="form-control otp-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required dir="ltr">@error('code')<small class="field-error">{{ $message }}</small>@enderror<button type="submit" class="btn btn-primary btn-block">تحقق</button></form>
<div class="registration-actions"><form method="POST" action="{{ route('register.resend') }}" data-registration-submit>@csrf<button class="btn btn-outline" type="submit" id="resend-code" data-resend-at="{{ $resendAt }}">إعادة إرسال الرمز</button></form><form method="POST" action="{{ route('register.edit') }}">@csrf<button class="registration-link" type="submit">العودة لتصحيح البيانات وإبطال الرمز</button></form></div>
@else
<div class="registration-success" role="status"><span aria-hidden="true">✓</span><h2>اكتمل التسجيل</h2>
@if(auth()->user()->phone_verified_at)
<p>تم التحقق من رقم الجوال. لم تعتمد الهوية المدنية ولم يُؤكد البريد الإلكتروني.</p>
@else
<p>اكتمل التسجيل التجريبي المحلي. لم يتم التحقق من ملكية رقم الجوال أو اعتماد الهوية المدنية.</p>
@endif
<p>حالة الحساب: <strong>{{ auth()->user()->registration_status === 'initial_account' ? 'حساب أولي — إصدار بطاقة شخصية لأول مرة فقط' : 'بانتظار التحقق من الهوية لدى السجل المدني' }}</strong></p><a href="{{ route('citizen.dashboard') }}" class="btn btn-primary">الانتقال إلى حسابي</a></div>
@endif
</div></section>
@endsection
