(() => {
    document.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
    document.querySelector('[data-auto-open]')?.showModal();
    document.querySelector('[data-copy-code]')?.addEventListener('click', async () => {
        const input = document.querySelector('[data-local-code]');
        try { await navigator.clipboard.writeText(input.value); } catch { input.select(); }
    });
    const form = document.querySelector('[data-remote-evidence]');
    if (!form) return;
    const kind = form.querySelector('[data-document-kind]');
    const sync = () => {
        const birth = kind.value === 'birth_certificate';
        const back = form.querySelector('[data-card-back]');
        back.hidden = birth; back.querySelector('input').required = !birth; back.querySelector('input').disabled = birth;
        const declaration = form.querySelector('[data-birth-only]'); declaration.hidden = !birth;
        declaration.querySelector('input').required = birth; declaration.querySelector('input').disabled = !birth;
    };
    kind.addEventListener('change', sync); sync();
    let stream, generation = 0;
    const video = form.querySelector('[data-selfie-video]');
    const capture = form.querySelector('[data-capture-selfie]');
    const status = form.querySelector('[data-camera-status]');
    const stop = () => { generation++; stream?.getTracks().forEach(track => track.stop()); stream = null; video.srcObject = null; video.hidden = true; capture.hidden = true; };
    const start = form.querySelector('[data-start-selfie]');
    start.addEventListener('click', async () => {
        stop();
        const ticket = generation;
        start.disabled = true;
        try {
            const acquired = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1280}, height: {ideal: 960}}, audio: false});
            if (ticket !== generation || document.hidden) { acquired.getTracks().forEach(track => track.stop()); return; }
            stream = acquired;
            video.srcObject = stream; video.hidden = false; capture.hidden = false; status.textContent = 'أظهر وجهك والوثيقة بوضوح ثم التقط الصورة.';
        } catch { status.textContent = 'تعذر فتح الكاميرا. استخدم زر ملف السيلفي لالتقاطها بكاميرا الهاتف. فتح الكاميرا داخل الصفحة يحتاج HTTPS أو localhost وإذن المتصفح.'; }
        finally { start.disabled = false; }
    });
    capture.addEventListener('click', () => {
        if (!video.videoWidth || !video.videoHeight) return;
        const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => {
            if (!blob) return;
            const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'selfie.jpg', {type: 'image/jpeg'}));
            form.querySelector('[data-selfie-file]').files = transfer.files; stop(); status.textContent = 'تم التقاط السيلفي وإرفاقه. راجع بقية المستندات ثم أرسل الطلب.';
        }, 'image/jpeg', 0.92);
    });
    window.addEventListener('pagehide', stop);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    form.addEventListener('submit', stop);
})();
