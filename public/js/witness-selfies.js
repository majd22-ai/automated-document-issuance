(() => {
    document.querySelectorAll('[data-witness-selfie]').forEach(root => {
        const input = root.querySelector('input[type="file"]');
        const preview = root.querySelector('[data-selfie-preview]');
        const saved = preview ? preview.getAttribute('src') : null;
        let url;
        const release = () => { if (url) URL.revokeObjectURL(url); url = undefined; };
        const dialog = root.querySelector('dialog');
        const openButton = root.querySelector('[data-open-selfie-picker]');
        if (dialog && openButton) {
            openButton.addEventListener('click', () => {
                if (typeof dialog.showModal === 'function') dialog.showModal();
                else dialog.setAttribute('open', '');
            });
            root.querySelectorAll('[data-selfie-source]').forEach(button => {
                button.addEventListener('click', () => {
                    if (button.dataset.selfieSource === 'camera') input.setAttribute('capture', 'environment');
                    else input.removeAttribute('capture');
                    if (typeof dialog.close === 'function') dialog.close();
                    else dialog.removeAttribute('open');
                    input.click();
                });
            });
            root.querySelector('[data-close-selfie-picker]').addEventListener('click', () => {
                if (typeof dialog.close === 'function') dialog.close();
                else dialog.removeAttribute('open');
            });
        }
        input.addEventListener('change', () => {
            release();
            const file = input.files[0];
            if (file && ['image/jpeg', 'image/png'].includes(file.type)) {
                url = URL.createObjectURL(file);
                if (preview) {
                    preview.src = url;
                    preview.hidden = false;
                }
            } else {
                if (preview) {
                    if (saved) preview.src = saved;
                    else preview.removeAttribute('src');
                    preview.hidden = !saved;
                }
            }
        });
        window.addEventListener('pagehide', release);
    });
})();
