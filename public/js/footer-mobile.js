(() => {
  document.querySelectorAll('.site-footer').forEach((footer) => {
    const mobile = window.matchMedia('(max-width: 767px)');
    const buttons = [...footer.querySelectorAll('.footer-toggle')];
    const close = (button) => {
      button.setAttribute('aria-expanded', 'false');
      button.closest('.footer-col').classList.remove('is-open');
    };
    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        if (!mobile.matches) return;
        const open = button.getAttribute('aria-expanded') !== 'true';
        buttons.forEach(close);
        if (open) {
          button.setAttribute('aria-expanded', 'true');
          button.closest('.footer-col').classList.add('is-open');
        }
      });
    });
    footer.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || !mobile.matches) return;
      const button = buttons.find((item) => item.getAttribute('aria-expanded') === 'true');
      if (button) {
        close(button);
        button.focus();
      }
    });
    footer.classList.add('footer-ready');
  });
})();
