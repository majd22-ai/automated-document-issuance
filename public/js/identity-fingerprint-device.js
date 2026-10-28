(() => {
    const root = document.querySelector('[data-fingerprint-device]');
    if (!root) return;
    const form = root.querySelector('[data-fingerprint-form]');
    const state = root.querySelector('[data-fingerprint-state]');
    const result = root.querySelector('[data-fingerprint-result]');
    const button = root.querySelector('[data-fingerprint-start]');
    const progress = root.querySelector('[data-fingerprint-progress]');
    let running = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (running || button.disabled) return;
        running = true;
        button.disabled = true;
        root.classList.add('is-running');
        state.textContent = 'جارٍ تنفيذ المحاكاة المحلية…';
        if (progress) progress.value = 25;
        result.replaceChildren();
        let completed = false;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'تعذر تنفيذ العملية.'];
                errors.forEach(message => { const p = document.createElement('p'); p.className = 'workflow-error'; p.textContent = message; result.append(p); });
                state.textContent = 'لم تسجل نتيجة جديدة. راجع الرسائل وحدّث الحالة.';
                return;
            }
            completed = true;
            if (progress) progress.value = 100;
            state.textContent = payload.result === 'success' ? 'اكتملت المحاكاة بنجاح' : 'انتهت المحاكاة بنتيجة فشل';
            [payload.reference, payload.result_label, payload.source_label, payload.executed_at, payload.operator].forEach(value => {
                const p = document.createElement('p'); p.textContent = value == null ? '' : String(value); result.append(p);
            });
            const link = document.createElement('a');
            link.textContent = 'تحديث الحالة وفتح سجل العملية';
            link.className = 'btn btn-outline';
            let target;
            try { target = new URL(payload.redirect_url, window.location.href); } catch { target = null; }
            link.href = target && target.origin === window.location.origin && ['http:', 'https:'].includes(target.protocol) ? target.href : window.location.href;
            result.append(link);
        } catch {
            state.textContent = 'تعذر تأكيد نتيجة العملية. حدّث الحالة قبل إعادة المحاولة.';
            const p = document.createElement('p'); p.className = 'workflow-error'; p.textContent = 'قد تكون العملية وصلت إلى الخادم؛ استخدم تحديث الحالة لمراجعة السجل.'; result.append(p);
            // Keep the same token if the request is retried after a transport error.
        } finally {
            running = false;
            root.classList.remove('is-running');
            button.disabled = completed;
            button.textContent = completed ? 'تم إرسال العملية — حدّث الحالة' : 'إعادة المحاولة بنفس المرجع';
        }
    });
})();
