(() => {
  'use strict';
  const form = document.querySelector('[data-linked-register]');
  function sync() {
    const path = form?.querySelector('[name="identity_path"]:checked')?.value;
    for (const [selector, visible] of [['[data-card-fields]', path === 'has_identity'], ['[data-family-fields]', path === 'no_identity']]) {
      form?.querySelectorAll(selector).forEach(group => {
        group.hidden = !visible;
        group.querySelectorAll('input, select').forEach(input => { input.disabled = !visible; });
      });
    }
  }
  form?.addEventListener('change', sync); sync();
  document.querySelectorAll('[data-dialog-open]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.dialogOpen)?.showModal()));
  document.querySelectorAll('dialog[data-dialog-auto]').forEach(d => d.showModal());
  document.querySelectorAll('[data-copy-local]').forEach(b => b.addEventListener('click', async () => {
    const input = b.closest('dialog').querySelector('[data-copy-value]'); input.select();
    try { await navigator.clipboard.writeText(input.value); b.textContent = 'تم النسخ'; } catch { b.textContent = 'حدد الرمز وانسخه'; }
  }));
  let stream;
  const video = document.querySelector('[data-live-video]');
  const message = document.querySelector('[data-live-message]');
  const photoDialog = document.querySelector('#activation-photo-source');
  const photoInput = document.querySelector('#mock-selfie');
  const cameraInput = document.querySelector('[data-photo-camera]');
  function photoSelected() {
    const label = document.querySelector('[data-photo-name]');
    if (label) label.textContent = photoInput?.files?.[0] ? 'تم اختيار الصورة: ' + photoInput.files[0].name : 'لم تُحدد صورة بعد.';
  }
  document.querySelector('[data-photo-close]')?.addEventListener('click', () => photoDialog?.close());
  document.querySelector('[data-photo-gallery]')?.addEventListener('click', () => { photoDialog?.close(); stop(); photoInput?.click(); });
  photoInput?.addEventListener('change', photoSelected);
  photoInput?.addEventListener('invalid', () => { photoDialog?.showModal(); if (message) message.textContent = 'اختر صورة أولًا لإرسال الطلب.'; });
  cameraInput?.addEventListener('change', () => { if (cameraInput.files.length) { photoInput.files = cameraInput.files; photoSelected(); } });
  document.querySelector('[data-live-start]')?.addEventListener('click', async () => {
    photoDialog?.close(); stop();
    if (!navigator.mediaDevices?.getUserMedia && cameraInput) { cameraInput.click(); return; }
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 800, height: 800 }, audio: false }); video.srcObject = stream; video.hidden = false; document.querySelector('[data-live-capture]').hidden = false; }
    catch { message.textContent = 'تعذر فتح الكاميرا. اسمح بالوصول إليها واستخدم HTTPS أو localhost، أو اختر صورة حديثة من المعرض.'; }
  });
  document.querySelector('[data-live-capture]')?.addEventListener('click', () => {
    if (!video.videoWidth) return;
    const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => { if (!blob) return; const files = new DataTransfer(); files.items.add(new File([blob], 'capture.jpg', {type:'image/jpeg'})); document.querySelector('[name="selfie"]').files = files.files; photoSelected(); message.textContent = 'تم التقاط الصورة. يمكنك الآن إرسالها لاستكمال التفعيل.'; stop(); }, 'image/jpeg', .9);
  });
  function stop() { stream?.getTracks().forEach(t => t.stop()); if (video) { video.srcObject = null; video.hidden = true; } const capture = document.querySelector('[data-live-capture]'); if (capture) capture.hidden = true; }
  window.addEventListener('pagehide', stop); document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
