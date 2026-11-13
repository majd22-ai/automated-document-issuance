document.querySelectorAll('[data-card-camera]').forEach(form => {
  const video = form.querySelector('video');
  const start = form.querySelector('[data-camera-start]');
  const capture = form.querySelector('[data-camera-capture]');
  const status = form.querySelector('[data-camera-status]');
  let stream;
  const stop = () => { stream?.getTracks().forEach(track => track.stop()); video.srcObject = null; video.hidden = true; capture.hidden = true; stream = null; };
  start.addEventListener('click', async () => {
    stop();
    try {
      stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1200}, height: {ideal: 1200}}, audio: false});
      video.srcObject = stream; video.hidden = false; capture.hidden = false; status.textContent = 'التقط صورة واضحة للوجه ثم احفظها وراجعها.';
    } catch { status.textContent = 'تعذر فتح الكاميرا. تحقق من الإذن أو اختر ملف الصورة الملتقطة.'; }
  });
  capture.addEventListener('click', () => {
    if (!video.videoWidth || !video.videoHeight) return;
    const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
      if (!blob) return;
      const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'identity-card-photo.jpg', {type: 'image/jpeg'}));
      form.querySelector('input[type=file]').files = transfer.files; stop(); status.textContent = 'التقطت الصورة؛ اضغط حفظ دليل جديد ثم راجع النتيجة.';
    }, 'image/jpeg', 0.92);
  });
  window.addEventListener('pagehide', stop);
  form.addEventListener('submit', stop);
});
