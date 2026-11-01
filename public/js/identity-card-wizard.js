(() => {
    const input = document.querySelector('[data-identity-photo]');
    const preview = document.querySelector('[data-identity-photo-preview]');
    if (!input || !preview) return;
    let submitting = false;
    input.form?.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        submitting = true;
        const status = document.querySelector('[data-photo-check-status]');
        if (status && input.files.length) status.hidden = false;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        const status = document.querySelector('[data-photo-check-status]');
        if (status) status.hidden = true;
    });
    const savedSource = preview.getAttribute('src');
    let objectUrl;
    const release = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = undefined; };
    input.addEventListener('change', () => {
        release();
        const file = input.files[0];
        if (file && file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
        } else {
            if (savedSource) preview.src = savedSource;
            else preview.removeAttribute('src');
            preview.hidden = !savedSource;
        }
    });
    window.addEventListener('pagehide', release);
})();
