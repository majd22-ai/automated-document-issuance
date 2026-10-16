document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.gov-nav-toggle');
  const links = document.querySelector('.gov-nav-links');
  const dropdowns = document.querySelectorAll('.gov-nav-dropdown');

  function closeDropdowns() {
    dropdowns.forEach(item => {
      item.classList.remove('is-open');
      item.querySelector('button')?.setAttribute('aria-expanded', 'false');
    });
  }

  toggle?.addEventListener('click', () => {
    const open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    if (!open) closeDropdowns();
  });

  dropdowns.forEach(item => {
    item.querySelector('button')?.addEventListener('click', () => {
      const open = !item.classList.contains('is-open');
      closeDropdowns();
      item.classList.toggle('is-open', open);
      item.querySelector('button').setAttribute('aria-expanded', String(open));
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('.gov-nav-dropdown')) closeDropdowns();

    if (!event.target.closest('.gov-main-nav')) {
      links?.classList.remove('is-open');
      toggle?.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    const dropdown = document.activeElement?.closest('.gov-nav-dropdown.is-open');
    closeDropdowns();

    if (dropdown) {
      dropdown.querySelector('button')?.focus();
    } else if (links?.classList.contains('is-open')) {
      links.classList.remove('is-open');
      toggle?.setAttribute('aria-expanded', 'false');
      toggle?.focus();
    }
  });
});