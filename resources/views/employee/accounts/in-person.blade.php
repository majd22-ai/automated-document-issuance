@push('styles')
<link rel="stylesheet" href="{{ asset('css/activation-visit.css') }}?v={{ filemtime(public_path('css/activation-visit.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/activation-visit.js') }}?v={{ filemtime(public_path('js/activation-visit.js')) }}" defer></script>
@endpush
<section class="card ops-card activation-flow" data-activation-flow
 data-prepare-url="{{ route('employee.accounts.prepare',$citizen) }}" data-finger-url="{{ route('employee.accounts.finger',$citizen) }}"
 data-code-url="{{ route('employee.accounts.code',$citizen) }}" data-verify-url="{{ route('employee.accounts.verify-code',$citizen) }}"
 data-local-enabled="{{ \App\Services\Registration\InPersonActivation::local() ? '1':'0' }}" data-csrf="{{ csrf_token() }}" data-token="{{ $visitReady ? $visit->visit_token : '' }}"
 data-right-token="{{ \Illuminate\Support\Str::uuid() }}" data-left-token="{{ \Illuminate\Support\Str::uuid() }}"
 data-right-passed="{{ $rightPassed ? '1':'0' }}" data-left-passed="{{ $leftPassed ? '1':'0' }}" data-has-code="{{ $visitReady && $visit->otp_hash ? '1':'0' }}">
<button type="button" class="btn btn-primary" data-activation-open>تفعيل حساب المواطن</button>
<dialog class="activation-flow-dialog" data-activation-dialog dir="rtl" aria-labelledby="activation-flow-title" @if($errors->any()) data-auto-open @endif>
 <header class="activation-dialog-head"><h2 id="activation-flow-title">تفعيل حساب المواطن</h2><button type="button" data-activation-close aria-label="إغلاق نافذة التفعيل">×</button></header>
 <p>{{ $citizen->name }} — الحساب الحالي #{{ $citizen->id }}</p>
 <p class="activation-local-note">تجربة محلية: البصمتان محاكاة فقط، ورمز الرسالة التجريبي لا يُرسل إلى شبكة الهاتف. لا تُحفظ صور أو قوالب بصمات.</p>
 <ol class="activation-step-list"><li data-step-label="identity">الهوية والهاتف</li><li data-step-label="fingers">البصمتان</li><li data-step-label="verify">رمز التحقق</li><li data-step-label="final">التفعيل</li></ol>
 <p data-activation-feedback role="status" aria-live="polite"></p>
 <section data-activation-step="identity">
  <form data-activation-identity method="POST" action="{{ route('employee.accounts.prepare',$citizen) }}">
   @csrf<input type="hidden" name="identity_type" value="{{ $citizen->national_id ? 'national_id':'birth_certificate' }}">
   <label for="activation-identity-number">{{ $citizen->national_id ? 'أدخل الرقم الوطني للمواطن':'رقم شهادة الميلاد — مسار الحساب الأولي' }}</label>
   <input id="activation-identity-number" class="form-control" name="identity_number" inputmode="numeric" pattern="[0-9]{5,50}" maxlength="50" required value="" autocomplete="off">
   <p class="activation-field-error" data-error="identity_number"></p>
   <label for="activation-phone">أدخل رقم جوال المواطن المسجل في الحساب</label><input id="activation-phone" class="form-control" name="phone" type="tel" dir="ltr" required autocomplete="off" placeholder="7XXXXXXXX أو +9677XXXXXXXX" value="">
   <p class="activation-field-error" data-error="phone"></p>
   <p>أدخل الرقم الوطني والجوال بنفسك. يجب أن يطابقا بيانات المواطن قبل الانتقال إلى البصمتين.</p><p class="activation-field-error" data-error="phone_change_confirmed"></p>
   <button class="btn btn-primary" type="submit">تحقق من الرقم واحفظ بيانات الحضور</button>
  </form>
 </section>
 <section data-activation-step="fingers" hidden>
  <p data-activation-registry></p><p>اضغط باستمرار على ماسح كل إصبع لمدة ٦ ثوانٍ، اليمنى ثم اليسرى. رفع الإصبع مبكرًا يلغي المحاولة. محاكاة محلية وليست بصمة حيوية.</p>
  <div class="activation-finger-grid">
   @foreach(['right_index'=>'السبابة اليمنى','left_index'=>'السبابة اليسرى'] as $finger=>$label)
   <button type="button" class="activation-finger-box" data-finger="{{ $finger }}" @disabled(!\App\Services\Registration\InPersonActivation::local())>
    <svg viewBox="0 0 64 64" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 29c0-27 40-27 40 0M18 34v-6c0-19 28-19 28 0v11M24 38V27c0-10 16-10 16 0v14M30 54c2-8 2-17 2-25M22 48c3-6 2-10 2-10M39 52c2-4 3-8 3-12M48 49c3-6 4-12 4-18M14 37c0 7-1 11-3 15"/></svg>
    <strong>{{ $label }}</strong><span data-finger-check hidden aria-label="نجحت المحاكاة">✓</span><span data-finger-status>اضغط مطولًا لمدة ٦ ثوانٍ</span>
   </button>
   @endforeach
  </div>
  <button type="button" class="btn btn-primary" data-activation-send disabled>موافق — إرسال رمز التحقق التجريبي</button>
  <button type="button" class="btn btn-outline" data-activation-edit>تعديل بيانات الهوية والهاتف</button>
 </section>
 <section data-activation-step="verify" hidden>
  <p>أدخل الرمز المكون من ٦ أرقام الذي يخبرك به المواطن.</p><button type="button" class="btn btn-outline" data-message-reopen hidden>عرض الرسالة التجريبية</button>
  <form data-activation-verify method="POST" action="{{ route('employee.accounts.verify-code',$citizen) }}">@csrf<input type="hidden" name="visit_token" value="{{ $visitReady ? $visit->visit_token : '' }}"><label for="activation-otp">رمز التحقق</label><input class="form-control" id="activation-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required><p class="activation-field-error" data-error="otp"></p><button class="btn btn-primary" type="submit">موافق — التحقق من الرمز</button></form>
  <button type="button" class="btn btn-outline" data-activation-resend>إعادة إرسال الرمز التجريبي</button>
 </section>
 <section data-activation-step="final" hidden><p class="alert alert-success">نجحت خطوات الحضور والرمز. أكمل المراجعة المطلوبة ثم اضغط «تفعيل الحساب».</p>
  @include('employee.accounts.confirmation')
 </section>
 <noscript><p>يلزم تشغيل JavaScript لاستخدام نافذة التفعيل.</p></noscript>
</dialog>
<dialog class="activation-message-dialog" data-activation-message aria-labelledby="activation-message-title" dir="rtl">
 <header class="activation-dialog-head"><h2 id="activation-message-title">محاكاة رسالة جوال المواطن</h2><button type="button" data-message-close aria-label="إغلاق الرسالة">×</button></header>
 <p>إلى: <bdi data-message-phone>{{ $visitReady ? $visit->visit_phone : '' }}</bdi></p><p>رمز التحقق لتفعيل حسابك في منصة الإصدار الآلي:</p><output data-message-code dir="ltr"></output>
 <button type="button" class="btn btn-outline" data-message-copy>نسخ الرمز</button><p data-message-status role="status">رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف.</p>
</dialog>
@if($fingerAttempts->isNotEmpty())<details><summary>سجل محاولات البصمتين السابقة</summary><ul>@foreach($fingerAttempts as $attempt)<li>{{ $attempt->finger === 'right_index' ? 'اليمنى':'اليسرى' }} — {{ $attempt->result === 'success' ? 'نجحت المحاكاة':'فشلت المحاكاة' }} — {{ $attempt->performed_at }}</li>@endforeach</ul></details>@endif
</section>
