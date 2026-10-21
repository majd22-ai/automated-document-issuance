(() => {
  'use strict';

  document.querySelectorAll('.auth-form input[type="password"]').forEach((input) => {
    if (input.closest('.password-field')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.before(wrapper);
    wrapper.append(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', 'عرض كلمة المرور');
    button.title = 'عرض كلمة المرور';
    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path data-eye-slash d="m3 3 18 18" hidden/></svg>';
    button.addEventListener('click', () => {
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      const label = visible ? 'إخفاء كلمة المرور' : 'عرض كلمة المرور';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', label);
      button.title = label;
      button.querySelector('[data-eye-slash]').toggleAttribute('hidden', !visible);
    });
    wrapper.append(button);
  });
})();
