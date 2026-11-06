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
  document.querySelector('[data-live-start]')?.addEventListener('click', async () => {
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 800, height: 800 }, audio: false }); video.srcObject = stream; video.hidden = false; document.querySelector('[data-live-capture]').hidden = false; }
    catch { message.textContent = 'تعذر فتح الكاميرا. يلزم HTTPS أو localhost وإذن الكاميرا. يمكنك الالتقاط من حقل الصورة عند دعم الجهاز.'; }
  });
  document.querySelector('[data-live-capture]')?.addEventListener('click', () => {
    if (!video.videoWidth) return;
    const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => { const files = new DataTransfer(); files.items.add(new File([blob], 'capture.jpg', {type:'image/jpeg'})); document.querySelector('[name="selfie"]').files = files.files; message.textContent = 'تم التقاط الصورة. أرسلها لتنفيذ المحاكاة.'; stop(); }, 'image/jpeg', .9);
  });
  function stop() { stream?.getTracks().forEach(t => t.stop()); if (video) { video.srcObject = null; video.hidden = true; } }
  window.addEventListener('pagehide', stop); document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
