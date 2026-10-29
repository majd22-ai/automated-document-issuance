(() => {
  'use strict';
  const root = document.getElementById('activation-checks');
  if (!root) return;
  const dialog = document.getElementById('activation-dialog');
  const title = document.getElementById('activation-dialog-title');
  const message = document.getElementById('activation-dialog-message');
  const feedback = document.getElementById('activation-feedback');
  const controls = document.getElementById('activation-finger-controls');
  const preview = document.getElementById('activation-code-preview');
  const code = document.getElementById('activation-code');
  const start = document.getElementById('activation-start');
  const send = document.getElementById('activation-send');
  const otp = document.getElementById('activation-otp');
  const confirm = document.getElementById('activation-confirm');
  let finger, token, running = false, reload = false;
  async function post(url, payload) {
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) throw new Error('وجهة غير مسموحة.');
    const response = await fetch(target, { method: 'POST', credentials: 'same-origin', headers: {
      'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf
    }, body: JSON.stringify({ ...payload, visit_token: root.dataset.token }) });
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'تعذرت العملية. حدّث الصفحة وحاول مجددًا.');
    return result;
  }
  root.querySelectorAll('[data-finger]').forEach(button => button.addEventListener('click', () => {
    finger = button.dataset.finger; token = crypto.randomUUID();
    title.textContent = finger === 'right_index' ? 'محاكاة السبابة اليمنى' : 'محاكاة السبابة اليسرى';
    message.textContent = 'محاكاة محلية؛ لا تُسجل بصمة حيوية حقيقية.';
    controls.hidden = false; preview.hidden = true; start.disabled = false;
    document.getElementById('activation-failure').checked = false;
    dialog.showModal();
  }));
  start.addEventListener('click', async () => {
    if (running) return;
    running = true; start.disabled = true; message.textContent = 'جارٍ تنفيذ المحاكاة على الخادم…';
    try {
      const result = await post(root.dataset.fingerUrl, { finger, operation_token: token, force_failure: document.getElementById('activation-failure').checked });
      message.textContent = result.result === 'success' ? 'نجحت محاكاة هذا الإصبع وحُفظت نتيجتها.' : 'فشلت المحاكاة التجريبية. يمكنك إعادة المحاولة.';
      if (result.result === 'success') { reload = true; } else { token = crypto.randomUUID(); start.disabled = false; }
    } catch (error) { message.textContent = error.message; start.disabled = false; }
    finally { running = false; }
  });
  send.addEventListener('click', async () => {
    if (running) return;
    running = true; send.disabled = true; feedback.textContent = 'جارٍ طلب الرمز…';
    try {
      const result = await post(root.dataset.codeUrl, {});
      feedback.textContent = result.message;
      if (otp) otp.disabled = false;
      if (result.source === 'local_trial' && result.local_code) {
        title.textContent = 'رمز التحقق التجريبي المحلي'; message.textContent = result.message;
        controls.hidden = true; preview.hidden = false; code.textContent = result.local_code;
        dialog.showModal();
      }
    } catch (error) { feedback.textContent = error.message; }
    finally { running = false; send.disabled = false; }
  });
  document.getElementById('activation-close').addEventListener('click', () => { if (!running) dialog.close(); });
  dialog.addEventListener('cancel', event => { if (running) event.preventDefault(); });
  dialog.addEventListener('close', () => {
    code.textContent = '';
    if (reload) window.location.reload(); else otp?.focus();
  });
  document.getElementById('activation-copy').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(code.textContent); message.textContent = 'نُسخ الرمز التجريبي.'; }
    catch { const range = document.createRange(); range.selectNodeContents(code); const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range); message.textContent = 'حدد الرمز وانسخه يدويًا.'; }
  });
  otp?.addEventListener('input', () => { confirm.disabled = otp.disabled || !/^[0-9]{6}$/.test(otp.value); });
})();
