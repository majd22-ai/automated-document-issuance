(() => {
    const root = document.querySelector('[data-attestation-simulator]');
    if (!root) return;
    const dialog = root.querySelector('dialog');
    const list = root.querySelector('[data-attestation-notifications]');
    const count = root.querySelector('[data-attestation-count]');
    const status = root.querySelector('[data-attestation-feed-status]');
    const seen = new Set();
    let running = false;
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
        if (document.hidden || running) return;
        running = true;
        try {
            const response = await fetch(root.dataset.feedUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
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
        } catch {
            status.textContent = 'تعذر تحديث الإشعارات. ستتم إعادة المحاولة تلقائيًا.';
        } finally { running = false; }
    }
    refresh();
    window.setInterval(refresh, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
