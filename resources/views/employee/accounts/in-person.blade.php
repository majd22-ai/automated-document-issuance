<section class="activation-visit card ops-card" style="padding:24px;margin-block:20px" aria-label="خطوات اعتماد الحضور">
  <h2>اعتماد الحضور خطوة بخطوة</h2>
  <ol><li>بيانات الهوية والهاتف</li><li>محاكاة السبابة اليمنى</li><li>محاكاة السبابة اليسرى</li><li>إرسال أو محاكاة إرسال الرمز</li><li>إدخال الرمز وتأكيد الاعتماد</li></ol>
  <p class="alert alert-info">البصمة هنا محاكاة محلية فقط وليست تحققًا حيويًا أو حكوميًا. الحساب الأولي يتطلب الإصدار الرسمي قبل اعتماده.</p>
  <form method="POST" action="{{ route('employee.accounts.prepare', $citizen) }}">
    @csrf
    <label for="visit-identity-type">نوع الرقم</label>
    <select id="visit-identity-type" name="identity_type" class="form-control"><option value="{{ $citizen->national_id ? 'national_id' : 'birth_certificate' }}">{{ $citizen->national_id ? 'رقم وطني مسجل في الحساب' : 'رقم شهادة الميلاد — مراجعة الموظف' }}</option></select>
    <label for="visit-identity-number">{{ $citizen->national_id ? 'الرقم الوطني المسجل' : 'رقم شهادة الميلاد' }}</label>
    <input id="visit-identity-number" name="identity_number" class="form-control" inputmode="numeric" pattern="[0-9]{5,50}" required value="{{ old('identity_number', $citizen->national_id ?: $citizen->birth_certificate_number) }}" @readonly($citizen->national_id)>
    @error('identity_number')<p class="text-danger">{{ $message }}</p>@enderror
    <label for="visit-phone">رقم الهاتف المراد التحقق منه (+967 ثم 9 أرقام)</label>
    <input id="visit-phone" name="phone" class="form-control" dir="ltr" type="tel" pattern="\+9677[0-9]{8}" required value="{{ old('phone', $citizen->phone) }}">
    @error('phone')<p class="text-danger">{{ $message }}</p>@enderror
    <p>الهاتف الحالي: <bdi>{{ $citizen->phone ?: 'غير مسجل' }}</bdi>. إذا عدّلته، فسيُحفظ الرقم الجديد عند نجاح الاعتماد ويُسجل التغيير في التدقيق.</p>
    <label><input type="checkbox" name="phone_change_confirmed" value="1"> أقر بتعديل رقم الهاتف المعروض على الحساب عند الاعتماد، إن اختلف عن الرقم الحالي.</label>
    @error('phone_change_confirmed')<p class="text-danger">{{ $message }}</p>@enderror
    <button class="btn btn-primary" type="submit">حفظ بيانات الحضور وبدء الخطوات</button>
    <p>إعادة حفظ البيانات تبدأ جلسة جديدة وتلغي صلاحية نتائج الجلسة السابقة دون حذف سجلها.</p>
  </form>
  @if($visitReady)
  <div id="activation-checks" data-finger-url="{{ route('employee.accounts.finger', $citizen) }}" data-code-url="{{ route('employee.accounts.code', $citizen) }}" data-token="{{ $visit->visit_token }}" data-csrf="{{ csrf_token() }}">
    <p>مصدر الهوية: {{ $visit->identity_source === 'account_national_id' ? 'الرقم الوطني المسجل في الحساب' : 'شهادة الميلاد راجعها الموظف' }} — الهاتف المقترح: <bdi>{{ $visit->visit_phone }}</bdi></p>
    <p>اليمنى: {{ $rightPassed ? 'نجحت محاكاة بصمة محلية' : 'بانتظار المحاكاة' }} — اليسرى: {{ $leftPassed ? 'نجحت محاكاة بصمة محلية' : 'بانتظار المحاكاة' }}</p>
    <button type="button" class="btn btn-primary" data-finger="right_index" @disabled($rightPassed || !\App\Services\Registration\InPersonActivation::local())>محاكاة السبابة اليمنى</button>
    <button type="button" class="btn btn-primary" data-finger="left_index" @disabled(!$rightPassed || $leftPassed || !\App\Services\Registration\InPersonActivation::local())>محاكاة السبابة اليسرى</button>
    <button type="button" class="btn btn-primary" id="activation-send" @disabled(!$rightPassed || !$leftPassed)>إرسال / محاكاة إرسال رمز التحقق</button>
    <p id="activation-feedback" role="status" aria-live="polite"></p>
    @if($visit->otp_source)<p>{{ $visit->otp_source === 'local_trial' ? 'رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف.' : 'رسالة SMS أُرسلت عبر المزود.' }}</p>@endif
  </div>
  <dialog id="activation-dialog" aria-labelledby="activation-dialog-title" style="width:min(92vw,480px);border:1px solid #ccc;border-radius:12px;padding:24px" dir="rtl">
    <button type="button" id="activation-close" aria-label="إغلاق النافذة">×</button>
    <h3 id="activation-dialog-title">محاكاة بصمة محلية</h3>
    <p id="activation-dialog-message" role="status" aria-live="polite"></p>
    <div id="activation-finger-controls"><label><input type="checkbox" id="activation-failure"> اختبار فشل المحاكاة لهذا الإصبع</label><button type="button" class="btn btn-primary" id="activation-start">بدء المحاكاة</button></div>
    <div id="activation-code-preview" hidden><output id="activation-code" dir="ltr" style="font-size:2rem;user-select:all"></output><button type="button" class="btn btn-primary" id="activation-copy">نسخ الرمز</button><p>رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف. يظهر مرة واحدة للموظف المخوّل.</p></div>
  </dialog>
  @endif
  @if($fingerAttempts->isNotEmpty())
  <details><summary>آخر محاولات الأصابع المسجلة (بما فيها الجلسات السابقة)</summary><ul>
    @foreach($fingerAttempts as $attempt)<li>{{ $attempt->finger === 'right_index' ? 'السبابة اليمنى' : 'السبابة اليسرى' }} — {{ $attempt->result === 'success' ? 'نجحت المحاكاة' : 'فشلت المحاكاة' }} — محاكاة بصمة محلية — الموظف #{{ $attempt->actor_id }} — {{ $attempt->performed_at }}</li>@endforeach
  </ul></details>
  @endif
</section>
@push('scripts')<script src="{{ asset('js/activation-visit.js') }}?v={{ filemtime(public_path('js/activation-visit.js')) }}" defer></script>@endpush
