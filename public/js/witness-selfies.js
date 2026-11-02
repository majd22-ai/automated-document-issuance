(() => {
    document.querySelectorAll('[data-witness-selfie]').forEach(root => {
        const input = root.querySelector('input[type="file"]');
        const preview = root.querySelector('[data-selfie-preview]');
        const saved = preview.getAttribute('src');
        let url;
        const release = () => { if (url) URL.revokeObjectURL(url); url = undefined; };
        input.addEventListener('change', () => {
            release();
            const file = input.files[0];
            if (file && ['image/jpeg', 'image/png'].includes(file.type)) {
                url = URL.createObjectURL(file);
                preview.src = url;
                preview.hidden = false;
            } else {
                if (saved) preview.src = saved;
                else preview.removeAttribute('src');
                preview.hidden = !saved;
            }
        });
        window.addEventListener('pagehide', release);
    });
})();
