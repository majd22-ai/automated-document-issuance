/* ============================================================
   GLOBAL APP JS
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* --- Mobile Navigation Toggle --- */
  const mobileToggle = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', () => {
      navLinks.classList.toggle('show-mobile');
      const expanded = navLinks.classList.contains('show-mobile');
      mobileToggle.setAttribute('aria-expanded', expanded);
    });
  }

  /* --- Form Validation --- */
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', function (e) {
      let valid = true;
      form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('is-invalid');
          valid = false;
        } else {
          field.classList.remove('is-invalid');
        }
      });
      if (!valid) {
        e.preventDefault();
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
      }
    });
  });

  /* --- Auto-dismiss Alerts --- */
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.4s, transform 0.4s';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(() => alert.remove(), 400);
    }, 5000);
  });

  /* --- File Upload Preview --- */
  document.querySelectorAll('.file-upload-input').forEach(input => {
    input.addEventListener('change', function () {
      const display = this.parentElement.querySelector('.file-name-display');
      if (display && this.files[0]) {
        display.textContent = this.files[0].name;
      }
    });
  });

  /* --- Password Visibility Toggle --- */
  document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.dataset.togglePassword);
      if (target) {
        target.type = target.type === 'password' ? 'text' : 'password';
        btn.classList.toggle('active');
      }
    });
  });

  /* --- Confirm Dialogs --- */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

  /* --- Table Row Click (optional) --- */
  document.querySelectorAll('tr[data-href]').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => {
      window.location.href = row.dataset.href;
    });
  });

});