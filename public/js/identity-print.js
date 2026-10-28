(() => {
    document.querySelectorAll('[data-identity-print]').forEach(button => {
        button.addEventListener('click', () => { if (!button.disabled) window.print(); });
    });
})();
