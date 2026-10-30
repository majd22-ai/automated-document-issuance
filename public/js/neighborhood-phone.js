(() => {
    document.querySelectorAll('[data-neighborhood-phone]').forEach(root => {
        const dialog = root.querySelector('[data-phone-dialog]');
        root.querySelector('[data-phone-open]').addEventListener('click', () => dialog.showModal());
        root.querySelector('[data-phone-close]').addEventListener('click', () => dialog.close());
        const request = root.querySelector('[data-phone-request]');
        const verification = root.querySelector('[data-phone-verification]');
        const rejection = root.querySelector('[data-phone-rejection]');
        const verify = () => {
            request.hidden = true;
            verification.hidden = false;
            root.querySelector('[data-phone-verification-title]').focus();
        };
        root.querySelector('[data-phone-approve]')?.addEventListener('click', verify);
        root.querySelector('[data-phone-reject]')?.addEventListener('click', () => {
            request.hidden = true;
            rejection.hidden = false;
            rejection.querySelector('textarea').focus();
        });
        const method = root.querySelector('[data-phone-method]');
        const sync = () => {
            const isCode = method.value === 'code';
            const panel = root.querySelector('[data-phone-code]');
            panel.hidden = !isCode;
            panel.querySelector('input').disabled = !isCode;
            panel.querySelector('input').required = isCode;
            root.querySelector('[data-phone-confirm]').textContent = isCode ? 'تحقق من الرمز واعتمد المصادقة التجريبية' : 'تأكيد البصمة التجريبية واعتماد المصادقة';
        };
        if (method) { method.addEventListener('change', sync); sync(); }
        root.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]').disabled = true;
        }));
        if (dialog.hasAttribute('data-auto-open') && verification) { dialog.showModal(); verify(); }
    });
})();
