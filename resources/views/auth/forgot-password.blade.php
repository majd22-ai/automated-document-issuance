@extends('layouts.auth')
@section('title', 'استعادة كلمة المرور - منصة الإصدار الآلي')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/local-password-recovery.css') }}?v={{ filemtime(public_path('css/local-password-recovery.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/local-password-recovery.js') }}?v={{ filemtime(public_path('js/local-password-recovery.js')) }}" defer></script>
@endpush
@section('content')
@php
  $localRecovery = \App\Services\Registration\LocalPasswordRecovery::enabled();
  $preview = $localRecovery && session()->has('local_recovery_preview') ? json_decode(\Illuminate\Support\Facades\Crypt::decryptString(session()->pull('local_recovery_preview')), true) : null;
@endphp
<div class="auth-wrapper"><div class="auth-card">
  <div class="auth-logo">
    <img class="auth-platform-logo" src="{{ asset('assets/images/logos/unified-issuance-logo.png') }}" alt="شعار مركز الإصدار الآلي الموحد">
    <h2>{{ $stage === 3 ? 'إنشاء كلمة مرور جديدة' : 'استعادة كلمة المرور' }}</h2>
    <p>{{ $stage === 1 ? 'أدخل البريد الإلكتروني المسجّل في حسابك.' : ($stage === 2 ? 'أدخل رمز الاستعادة بعد نسخه من النافذة المحلية.' : 'اختر كلمة مرور جديدة وأكدها لإكمال الاستعادة.') }}</p>
  </div>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @if($stage === 1)
  <form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf
    <div class="form-group"><label class="form-label" for="email">البريد الإلكتروني</label><input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">{{ $localRecovery ? 'طلب رمز الاستعادة المحلي' : 'إرسال رابط الاستعادة' }}</button>
  </form>
  @elseif($stage === 2)
  <p class="recovery-email" dir="ltr">{{ $recoveryEmail }}</p>
  <form method="POST" action="{{ route('password.local.verify') }}" class="auth-form">@csrf
    <div class="form-group"><label class="form-label" for="recovery-code">رمز الاستعادة</label><input type="text" id="recovery-code" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" dir="ltr" required></div>
    <p>الرمز صالح لعشر دقائق، وبحد أقصى خمس محاولات.</p>
    <button type="submit" class="btn btn-primary btn-block">التحقق والمتابعة</button>
  </form>
  <form method="POST" action="{{ route('password.email') }}">@csrf<input type="hidden" name="email" value="{{ $recoveryEmail }}"><button class="btn btn-outline btn-block" type="submit">طلب رمز جديد وإلغاء السابق</button></form>
  @else
  <form method="POST" action="{{ route('password.local.complete') }}" class="auth-form">@csrf
    <div class="form-group"><label class="form-label" for="new-password">كلمة المرور الجديدة</label><input type="password" id="new-password" name="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="255" required></div>
    <div class="form-group"><label class="form-label" for="new-password-confirmation">تأكيد كلمة المرور الجديدة</label><input type="password" id="new-password-confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" maxlength="255" required></div>
    <p>ثماني خانات على الأقل، تتضمن حروفًا وأرقامًا ورمزًا.</p>
    <button type="submit" class="btn btn-primary btn-block">حفظ كلمة المرور والعودة لتسجيل الدخول</button>
  </form>
  @endif
  <div class="auth-links"><a href="{{ route('login') }}">العودة إلى تسجيل الدخول</a><a href="{{ route('register') }}">إنشاء حساب جديد</a></div>
</div></div>
@if($preview)
<dialog id="recovery-preview" class="recovery-preview" open aria-labelledby="recovery-preview-title" aria-describedby="recovery-preview-description">
  <button type="button" class="recovery-close" data-recovery-close aria-label="إغلاق نافذة رمز الاستعادة">×</button>
  <h2 id="recovery-preview-title">رمز الاستعادة المحلي</h2>
  <p id="recovery-preview-description">تجربة محلية فقط؛ لم تُرسل رسالة فعلية. انسخ الرمز، أغلق النافذة بعلامة ×، ثم أدخله للمتابعة إلى تعيين كلمة مرور جديدة.</p>
  <p>البريد الإلكتروني</p><p dir="ltr" class="recovery-email">{{ $preview['email'] }}</p>
  <label for="local-recovery-copy-code">رمز الاستعادة</label>
  <input id="local-recovery-copy-code" class="form-control recovery-copy-code" readonly dir="ltr" value="{{ $preview['code'] }}" autocomplete="off">
  <button type="button" class="btn btn-secondary btn-block" data-recovery-copy>نسخ الرمز</button>
  <p data-recovery-copy-status role="status" aria-live="polite"></p>
</dialog>
@endif
@endsection
