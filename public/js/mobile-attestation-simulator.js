(() => {
    const root = document.querySelector('[data-attestation-simulator]');
    if (!root) return;
    if (root.dataset.pollingStarted) return;
    root.dataset.pollingStarted = 'true';
    const dialog = root.querySelector('dialog');
    const list = root.querySelector('[data-attestation-notifications]');
    const count = root.querySelector('[data-attestation-count]');
    const status = root.querySelector('[data-attestation-feed-status]');
    const seen = new Set();
    let running = false;
    let timer = null;
    let controller;
    let lastAttempt = -Infinity;
    let stopped = false;
    const open = () => { if (!dialog.open) dialog.showModal(); };
    root.querySelector('[data-attestation-toggle]').addEventListener('click', open);
    root.querySelector('[data-attestation-close]').addEventListener('click', () => dialog.close());
    const addText = (parent, tag, value) => {
        const element = document.createElement(tag);
        element.textContent = value == null ? '' : String(value);
        parent.append(element);
        return element;
    };
    async function refresh() {
        if (stopped || document.hidden || running || Date.now() - lastAttempt < 120000) return;
        running = true;
        lastAttempt = Date.now();
        controller = new AbortController();
        try {
            const response = await fetch(root.dataset.feedUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
            if ([401, 403, 419].includes(response.status)) { stopped = true; pause(); return; }
            if (!response.ok) throw new Error('feed');
            const feed = await response.json();
            const notifications = Array.isArray(feed.notifications) ? feed.notifications.slice(0, 30) : [];
            count.textContent = String(Math.max(0, Number(feed.unread) || 0));
            let newlyUnread = false;
            list.replaceChildren();
            notifications.forEach(notification => {
                const key = String(notification.id);
                if (!notification.read && !seen.has(key)) newlyUnread = true;
                seen.add(key);
                const item = document.createElement('li');
                item.className = notification.read ? '' : 'is-unread';
                addText(item, 'strong', notification.authority_label);
                addText(item, 'small', notification.read ? 'تمت قراءته' : 'جديد');
                addText(item, 'p', 'يوجد طلب مصادقة تجريبية مرتبط بهذا الإشعار.');
                addText(item, 'p', `المستلم: ${notification.recipient_name || 'غير مسجل'}`);
                addText(item, 'p', `مقدم الطلب: ${notification.citizen_name || 'غير مسجل'}`);
                addText(item, 'p', `مرجع الطلب: ${notification.request_reference || 'غير مسجل'}`);
                addText(item, 'small', notification.created_at);
                // Use a real same-origin POST form so the server marks read and redirects.
                let target;
                try { target = new URL(notification.open_url, window.location.href); } catch { target = null; }
                if (target && target.origin === window.location.origin && ['http:', 'https:'].includes(target.protocol)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = target.href;
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = root.dataset.csrf;
                    form.append(csrf);
                    const button = addText(form, 'button', 'عرض الطلب');
                    button.type = 'submit'; button.className = 'btn btn-outline';
                    item.append(form);
                }
                list.append(item);
            });
            status.textContent = notifications.length ? '' : 'لا توجد إشعارات مصادقة.';
            if (newlyUnread) open();
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = 'تعذر تحديث الإشعارات. ستتم إعادة المحاولة تلقائيًا.';
        } finally { running = false; }
    }
    function pause() {
        if (timer !== null) window.clearInterval(timer);
        timer = null;
        controller?.abort();
    }
    function resume() {
        if (stopped || document.hidden) return;
        if (timer === null) timer = window.setInterval(refresh, 120000);
        refresh();
    }
    document.addEventListener('visibilitychange', () => document.hidden ? pause() : resume());
    window.addEventListener('pagehide', pause);
    window.addEventListener('pageshow', resume);
    resume();
})();
