(() => {
    const dialog = document.querySelector('[data-attestation-reject-dialog]');
    const open = document.querySelector('[data-attestation-reject-open]');
    if (!dialog || !open) return;
    open.addEventListener('click', () => dialog.showModal());
    dialog.querySelector('[data-attestation-reject-close]').addEventListener('click', () => dialog.close());
})();
