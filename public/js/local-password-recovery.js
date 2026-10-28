(() => {
  'use strict';
  const modal = document.getElementById('recovery-preview');
  if (!modal) return;
  if (typeof modal.showModal === 'function') {
    modal.close();
    modal.showModal();
  }
  modal.querySelector('[data-recovery-close]').addEventListener('click', () => {
    if (typeof modal.close === 'function') modal.close();
    else modal.removeAttribute('open');
    (document.getElementById('recovery-code') || document.getElementById('code'))?.focus();
  });
  modal.addEventListener('close', () => (document.getElementById('recovery-code') || document.getElementById('code'))?.focus());
  modal.querySelector('[data-recovery-copy]').addEventListener('click', async () => {
    const input = document.getElementById('local-recovery-copy-code');
    const status = modal.querySelector('[data-recovery-copy-status]');
    try {
      await navigator.clipboard.writeText(input.value);
      status.textContent = 'تم نسخ الرمز. أغلق النافذة وأدخله للمتابعة.';
    } catch {
      input.focus();
      input.select();
      status.textContent = 'تم تحديد الرمز. اضغط Ctrl + C لنسخه.';
    }
  });
})();
