 document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.gov-nav-toggle');
  const links = document.querySelector('.gov-nav-links');
  const dropdowns = document.querySelectorAll('.gov-nav-dropdown');

  const desktop = window.matchMedia(
    '(min-width: 901px) and (hover: hover) and (pointer: fine)'
  );

  function setOpen(item, open) {
    item.classList.toggle('is-open', open);
    item.querySelector('button')?.setAttribute(
      'aria-expanded',
      String(open)
    );
  }

  function closeDropdowns() {
    dropdowns.forEach(item => setOpen(item, false));
  }

  function openDropdown(item) {
    closeDropdowns();
    setOpen(item, true);
  }

  toggle?.addEventListener('click', () => {
    if (!links) return;

    const open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));

    if (!open) closeDropdowns();
  });

  dropdowns.forEach(item => {
    item.addEventListener('pointerenter', () => {
      if (desktop.matches) openDropdown(item);
    });

    item.addEventListener('pointerleave', () => {
      if (desktop.matches && !item.contains(document.activeElement)) {
        setOpen(item, false);
      }
    });

    item.querySelector('button')?.addEventListener('click', () => {
      const open = !item.classList.contains('is-open');

      closeDropdowns();
      setOpen(item, open);
    });

    item.addEventListener('focusout', event => {
      if (!item.contains(event.relatedTarget)) {
        setOpen(item, false);
      }
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('.gov-nav-dropdown')) {
      closeDropdowns();
    }

    if (!event.target.closest('.gov-main-nav')) {
      links?.classList.remove('is-open');
      toggle?.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    const item = document.activeElement?.closest(
      '.gov-nav-dropdown.is-open'
    );

    closeDropdowns();

    if (item) {
      item.querySelector('button')?.focus();
    } else if (links?.classList.contains('is-open')) {
      links.classList.remove('is-open');
      toggle?.setAttribute('aria-expanded', 'false');
      toggle?.focus();
    }
  });

  document.querySelectorAll('[data-demo-form]').forEach(form => {
    form.addEventListener('submit', event => {
      event.preventDefault();

      form.querySelector('[data-demo-result]').textContent =
        'هذه واجهة تجريبية فقط؛ لم يتم إرسال البيانات أو البحث عن طلبات فعلية. يلزم ربط الخدمة بالنظام لتفعيلها.';
    });
  });
});