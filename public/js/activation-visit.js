(() => {
  'use strict';
  const root = document.querySelector('[data-activation-flow]');
  if (!root) return;
  const find = selector => root.querySelector(selector);
  const dialog = find('[data-activation-dialog]');
  const messageDialog = find('[data-activation-message]');
  const feedback = find('[data-activation-feedback]');
  const identityForm = find('[data-activation-identity]');
  const verifyForm = find('[data-activation-verify]');
  const finalForm = find('[data-activation-final]');
  const otp = verifyForm.querySelector('[name="otp"]');
  const confirm = find('#activation-confirm');
  const boxes = [...root.querySelectorAll('[data-finger]')];
  const passed = { right_index: false, left_index: false };
  const tokens = { right_index: root.dataset.rightToken, left_index: root.dataset.leftToken };
  let token = '', busy = false, currentStep = 'identity', hold = null;
  const digits = value => value.replace(/[٠-٩۰-۹]/g, char => String('٠١٢٣٤٥٦٧٨٩'.includes(char) ? '٠١٢٣٤٥٦٧٨٩'.indexOf(char) : '۰۱۲۳۴۵۶۷۸۹'.indexOf(char)));
  const step = name => {
    currentStep = name;
    root.querySelectorAll('[data-activation-step]').forEach(panel => { panel.hidden = panel.dataset.activationStep !== name; });
    root.querySelectorAll('[data-step-label]').forEach(label => {
      if (label.dataset.stepLabel === name) label.setAttribute('aria-current', 'step'); else label.removeAttribute('aria-current');
    });
    dialog.scrollTop = 0;
  };
  function sync() {
    boxes.forEach(box => {
      const success = passed[box.dataset.finger];
      box.disabled = root.dataset.localEnabled === '0' || busy || success || !token || (hold && hold.box !== box) || (box.dataset.finger === 'left_index' && !passed.right_index);
      box.classList.toggle('is-success', success);
      box.querySelector('[data-finger-check]').hidden = !success;
      if (hold?.box !== box) box.querySelector('[data-finger-status]').textContent = success ? 'نجحت المحاكاة وحُفظت' : 'اضغط مطولًا لمدة ٦ ثوانٍ';
    });
    find('[data-activation-send]').disabled = busy || !!hold || !passed.right_index || !passed.left_index;
  }
  function clearErrors() { root.querySelectorAll('[data-error]').forEach(node => { node.textContent = ''; }); }
  async function post(url, payload) {
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) throw new Error('وجهة غير مسموحة.');
    const response = await fetch(target, { method: 'POST', credentials: 'same-origin', headers: {
      'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf
    }, body: JSON.stringify({ ...payload, visit_token: token }) });
    let result;
    try { result = await response.json(); } catch { throw new Error('تعذر قراءة استجابة الخادم. حدّث الصفحة إذا انتهت الجلسة.'); }
    if (!response.ok) {
      root.querySelectorAll('[data-error]').forEach(node => { node.textContent = (result.errors?.[node.dataset.error] || []).join(' '); });
      throw new Error(Object.values(result.errors || {}).flat().join(' ') || (response.status === 419 ? 'انتهت جلسة الصفحة. حدّث الصفحة.' : 'تعذرت العملية. حاول مجددًا.'));
    }
    return result;
  }
  async function run(button, work) {
    if (busy || hold) return;
    busy = true; button.disabled = true; clearErrors(); sync();
    try { await work(); } catch (error) { feedback.textContent = error.message || 'تعذر الاتصال بالخادم.'; }
    finally { busy = false; button.disabled = false; sync(); }
  }
  find('[data-activation-open]').addEventListener('click', () => {
    if (busy) return;
    cancelHold(); token = ''; passed.right_index = false; passed.left_index = false;
    identityForm.reset(); confirm.disabled = true;
    finalForm.querySelector('[name="otp"]').value = ''; finalForm.querySelector('[name="visit_token"]').value = '';
    clearErrors(); feedback.textContent = ''; step('identity'); sync(); dialog.showModal();
  });
  find('[data-activation-close]').addEventListener('click', () => { if (!busy) dialog.close(); });
  dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
  dialog.addEventListener('close', () => { cancelHold(); find('[data-message-code]').textContent = ''; find('[data-message-reopen]').hidden = true; });
  find('[data-activation-edit]').addEventListener('click', () => { if (!busy) { cancelHold(); step('identity'); } });
  identityForm.addEventListener('submit', event => {
    event.preventDefault();
    const button = identityForm.querySelector('button[type="submit"]');
    return run(button, async () => {
      const data = Object.fromEntries(new FormData(identityForm));
      data.identity_number = digits(data.identity_number.trim()); data.phone = digits(data.phone.trim());
      feedback.textContent = 'جارٍ مطابقة الرقم وحفظ بيانات الحضور…';
      const result = await post(root.dataset.prepareUrl, data);
      token = result.visit_token; tokens.right_index = result.right_token; tokens.left_index = result.left_token;
      passed.right_index = false; passed.left_index = false;
      confirm.disabled = true; finalForm.querySelector('[name="visit_token"]').value = token;
      finalForm.querySelector('[name="otp"]').value = ''; otp.value = '';
      find('[data-message-code]').textContent = ''; find('[data-message-reopen]').hidden = true;
      find('[data-message-phone]').textContent = result.phone;
      find('[data-activation-registry]').textContent = result.registry.message;
      feedback.textContent = result.registry.message;
      step('fingers');
    });
  });
  function recordFinger(box) { return run(box, async () => {
    const finger = box.dataset.finger;
    box.classList.add('is-scanning');
    feedback.textContent = 'جارٍ تسجيل محاكاة الإصبع على الخادم…';
    try {
      const result = await post(root.dataset.fingerUrl, {finger, operation_token: tokens[finger]});
      if (result.result === 'success') {
        passed[finger] = true; feedback.textContent = 'نجحت محاكاة الإصبع وحُفظت نتيجتها.';
      } else {
        tokens[finger] = result.retry_token;
        feedback.textContent = 'لم تنجح المحاكاة؛ المس المربع لإعادة المحاولة.';
      }
    } finally { box.classList.remove('is-scanning'); if (!passed[finger]) box.style.removeProperty('--hold-progress'); }
  }); }
  function cancelHold() {
    if (!hold) return;
    clearInterval(hold.timer);
    hold.box.classList.remove('is-scanning');
    hold.box.style.removeProperty('--hold-progress');
    hold = null; feedback.textContent = 'أُلغيت المحاولة قبل اكتمال ٦ ثوانٍ. اضغط باستمرار لإعادة المحاكاة.'; sync();
  }
  function startHold(box) {
    if (box.disabled || busy || hold || currentStep !== 'fingers') return;
    const active = { box, started: Date.now(), timer: null };
    hold = active; box.classList.add('is-scanning'); sync();
    feedback.textContent = 'استمر بالضغط ٦ ثوانٍ. رفع الإصبع مبكرًا يلغي المحاكاة.';
    active.timer = setInterval(() => {
      if (hold !== active) return;
      const elapsed = Date.now() - active.started;
      box.style.setProperty('--hold-progress', `${Math.min(100, elapsed / 60)}%`);
      box.querySelector('[data-finger-status]').textContent = `استمر بالضغط… ${Math.min(6, Math.floor(elapsed / 1000))} / ٦ ثوانٍ`;
      if (elapsed >= 6000) {
        clearInterval(active.timer); hold = null;
        recordFinger(box);
      }
    }, 100);
  }
  boxes.forEach(box => {
    box.addEventListener('pointerdown', event => {
      if (event.button !== 0 || event.isPrimary === false) return;
      event.preventDefault();
      if (!box.disabled && !busy) { box.setPointerCapture?.(event.pointerId); startHold(box); }
    });
    ['pointerup', 'pointercancel', 'pointerleave', 'lostpointercapture', 'blur'].forEach(name => box.addEventListener(name, cancelHold));
    box.addEventListener('contextmenu', event => event.preventDefault());
    box.addEventListener('click', event => event.preventDefault());
    box.addEventListener('keydown', event => {
      if (![' ', 'Enter'].includes(event.key)) return;
      event.preventDefault(); if (!event.repeat) startHold(box);
    });
    box.addEventListener('keyup', event => { if ([' ', 'Enter'].includes(event.key)) { event.preventDefault(); cancelHold(); } });
  });
  window.addEventListener('blur', cancelHold);
  document.addEventListener('visibilitychange', () => { if (document.hidden) cancelHold(); });
  async function send(button) {
    await run(button, async () => {
      feedback.textContent = 'جارٍ إنشاء رمز التحقق…';
      const result = await post(root.dataset.codeUrl, {});
      confirm.disabled = true; finalForm.querySelector('[name="otp"]').value = ''; otp.value = '';
      feedback.textContent = result.message;
      step('verify');
      if (result.source === 'local_trial' && result.local_code) {
        find('[data-message-code]').textContent = result.local_code;
        find('[data-message-reopen]').hidden = false;
        messageDialog.showModal();
      }
    });
  }
  find('[data-activation-send]').addEventListener('click', event => send(event.currentTarget));
  find('[data-activation-resend]').addEventListener('click', event => send(event.currentTarget));
  find('[data-message-close]').addEventListener('click', () => { messageDialog.close(); otp.focus(); });
  find('[data-message-reopen]').addEventListener('click', () => messageDialog.showModal());
  find('[data-message-copy]').addEventListener('click', async () => {
    const code = find('[data-message-code]');
    try {
      if (!navigator.clipboard?.writeText) throw new Error();
      await navigator.clipboard.writeText(code.textContent);
      find('[data-message-status]').textContent = 'نُسخ الرمز التجريبي.';
    } catch {
      const range = document.createRange(); range.selectNodeContents(code);
      const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
      find('[data-message-status]').textContent = 'النسخ التلقائي غير متاح على هذا الرابط. الرمز محدد؛ انسخه بالضغط المطول.';
    }
  });
  verifyForm.addEventListener('submit', event => {
    event.preventDefault();
    return run(verifyForm.querySelector('button[type="submit"]'), async () => {
      const code = digits(otp.value.trim());
      if (!/^[0-9]{6}$/.test(code)) throw new Error('أدخل رمزًا من ستة أرقام.');
      const result = await post(root.dataset.verifyUrl, {otp: code});
      if (result.verified !== true) throw new Error('لم ينجح التحقق من الرمز.');
      finalForm.querySelector('[name="otp"]').value = code;
      finalForm.querySelector('[name="visit_token"]').value = token;
      confirm.disabled = false; feedback.textContent = result.message; step('final');
    });
  });
  finalForm.addEventListener('submit', event => {
    if (confirm.disabled || busy || currentStep !== 'final') event.preventDefault();
  });
  step(currentStep); sync();
  if (dialog.hasAttribute('data-auto-open')) dialog.showModal();
})();
