(function () {
  'use strict';
  const validPhone = value => /^7[0-9]{8}$/.test(value);
  const validPassword = value => [...value].length >= 8 && [...value].length <= 255 && /\p{L}/u.test(value) && /\p{N}/u.test(value) && /[\p{S}\p{P}]/u.test(value);
  if (typeof module !== 'undefined') module.exports = { validPhone, validPassword };
  if (typeof document === 'undefined') return;

  const form = document.getElementById('registration-form');
  if (form) {
    const get = id => document.getElementById(id);
    const dialog = get('identity-dialog');
    const path = get('identity_path');
    const nationalId = get('national_id');
    const phone = get('phone');
    const password = get('password');
    const passwordConfirmation = get('password_confirmation');
    const next = get('registration-next');
    let busy = false;
    let checkedCaptcha = '';
    let showFieldErrors = false;
    function fieldError(input) {
      const group = input.closest('.form-group') || input.closest('.captcha-section') || input.closest('.registration-terms');
      if (!group) return;
      let error = group.querySelector('[data-client-error]');
      if (!error) {
        error = document.createElement('small');
        error.className = 'field-error';
        error.dataset.clientError = 'true';
        error.id = `${input.id}-client-error`;
        error.setAttribute('role', 'alert');
        group.append(error);
        input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
      }
      error.textContent = !input.disabled && !input.checkValidity() ? input.validationMessage : '';
      input.setAttribute('aria-invalid', error.textContent ? 'true' : 'false');
    }
    function validateFields() {
      Array.from(form.elements).filter(input => input.matches('input:not([type="hidden"]),select,textarea')).forEach(fieldError);
    }
    function refresh() {
      const initial = path.value === 'no_identity';
      nationalId.required = true;
      nationalId.disabled = false;
      const fieldName = initial ? 'birth_certificate_number' : 'national_id';
      if (nationalId.name && nationalId.name !== fieldName) nationalId.value = '';
      nationalId.name = fieldName;
      nationalId.placeholder = initial ? 'أدخل رقم شهادة الميلاد الخاص بك' : 'أدخل رقم الهوية الخاص بك';
      nationalId.pattern = initial ? '[0-9]{1,50}' : '[0-9]{5,20}';
      nationalId.maxLength = initial ? 50 : 20;
      document.querySelector('label[for="national_id"]')?.replaceChildren(initial ? 'رقم شهادة الميلاد' : 'رقم الهوية');
      password.setCustomValidity(password.value && !validPassword(password.value) ? 'أدخل 8 خانات على الأقل تشمل حروفًا وأرقامًا ورموزًا.' : '');
      const mismatch = passwordConfirmation.value && password.value !== passwordConfirmation.value;
      passwordConfirmation.setCustomValidity(mismatch ? 'كلمتا المرور غير متطابقتين' : '');
      get('password-match').textContent = mismatch ? 'كلمتا المرور غير متطابقتين' : '';
      next.disabled = busy;
      if (showFieldErrors) validateFields();
    }
    get('no-identity').addEventListener('click', () => dialog.showModal());
    get('cancel-identity').addEventListener('click', () => dialog.close());
    get('identity-dialog-form').addEventListener('submit', event => {
      event.preventDefault();
      path.value = 'no_identity';
      dialog.close();
      phone.focus();
      refresh();
    });
    get('refresh-captcha').addEventListener('click', () => {
      get('captcha-image').src = get('captcha-image').src.split('?')[0] + '?v=' + Date.now();
      get('captcha').value = '';
      checkedCaptcha = '';
      get('captcha-status').textContent = '';
      refresh();
    });
    async function checkCaptcha() {
      const value = get('captcha').value;
      if (!/^[A-Za-z2-9]{6}$/.test(value)) { get('captcha-status').textContent = 'أدخل رمز الصورة المكون من 6 خانات.'; return false; }
      const button = get('check-captcha');
      button.disabled = true;
      checkedCaptcha = '';
      refresh();
      get('captcha-status').textContent = 'جارٍ التحقق…';
      try {
        const response = await fetch(form.dataset.captchaCheck, {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
          body: JSON.stringify({ captcha: value }),
          signal: typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(12000) : undefined,
        });
        if (response.status === 419) throw new Error('انتهت جلسة الصفحة. حدّث الصفحة وحاول مجددًا.');
        if (response.status === 429) throw new Error('محاولات كثيرة. انتظر دقيقة وحاول مجددًا.');
        const data = response.ok ? await response.json() : { valid: false };
        if (get('captcha').value === value && data.valid) checkedCaptcha = value.toUpperCase();
        get('captcha-status').textContent = checkedCaptcha ? 'تم التحقق من رمز الصورة.' : 'تعذّر التحقق. راجع الرمز أو حدّث الصورة وحاول مجددًا.';
      } catch (error) {
        get('captcha-status').textContent = error.message && /^(انتهت|محاولات)/.test(error.message) ? error.message : 'تعذّر الاتصال. أعد المحاولة.';
      } finally { button.disabled = false; refresh(); }
      return checkedCaptcha === get('captcha').value.toUpperCase() && !!checkedCaptcha;
    }
    get('check-captcha').addEventListener('click', checkCaptcha);
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', async event => {
      refresh();
      if (busy) { event.preventDefault(); return; }
      const feedback = get('registration-feedback');
      if (!form.checkValidity()) {
        event.preventDefault();
        showFieldErrors = true;
        validateFields();
        Array.from(form.elements).find(input => input.willValidate && !input.checkValidity())?.focus();
        feedback.textContent = 'يرجى استكمال الحقول المطلوبة وتصحيح الحقل المشار إليه، والموافقة على شروط الاستخدام.';
        return;
      }
      if (!checkedCaptcha || checkedCaptcha !== get('captcha').value.toUpperCase()) {
        event.preventDefault();
        busy = true;
        next.disabled = true;
        next.textContent = 'جارٍ التحقق…';
        feedback.textContent = 'جارٍ التحقق من رمز الصورة…';
        const valid = await checkCaptcha();
        busy = false;
        next.textContent = 'التالي';
        refresh();
        if (valid) { feedback.textContent = ''; form.requestSubmit(); }
        else feedback.textContent = get('captcha-status').textContent;
        return;
      }
      busy = true;
      feedback.textContent = 'جارٍ إرسال البيانات. ستظهر نتيجة الإرسال أو أي أخطاء من الخادم.';
      next.disabled = true;
      next.textContent = 'جارٍ التحقق وإرسال الرمز…';
    });
    // Browsers may restore a submitted document from back/forward cache.
    window.addEventListener('pageshow', () => { busy = false; next.textContent = 'التالي'; refresh(); });
    refresh();
  }
  document.querySelectorAll('[data-registration-submit]').forEach(otpForm => {
    otpForm.addEventListener('submit', () => {
      const button = otpForm.querySelector('button[type="submit"]');
      button.disabled = true;
      button.textContent = 'جارٍ المعالجة…';
    });
  });
  const resend = document.getElementById('resend-code');
  if (resend) {
    const tick = () => {
      const seconds = Math.max(0, Number(resend.dataset.resendAt) - Math.floor(Date.now() / 1000));
      resend.disabled = seconds > 0;
      resend.textContent = seconds > 0 ? `إعادة الإرسال بعد ${seconds} ثانية` : 'إعادة إرسال الرمز';
    };
    tick();
    window.setInterval(tick, 1000);
  }
})();
