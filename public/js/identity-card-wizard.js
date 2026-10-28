(() => {
    const input = document.querySelector('[data-identity-photo]');
    const preview = document.querySelector('[data-identity-photo-preview]');
    if (!input || !preview) return;
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
