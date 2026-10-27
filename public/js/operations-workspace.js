(() => {
  'use strict';
  const workspace = document.querySelector('.ops-content');
  if (!workspace) return;
  workspace.querySelectorAll('table').forEach(table => {
    if (table.closest('.ops-table-wrap')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'ops-table-wrap';
    wrapper.tabIndex = 0;
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || 'جدول البيانات، قابل للتمرير أفقيًا');
    table.before(wrapper);
    wrapper.append(table);
  });
  workspace.querySelectorAll('label:not([for])').forEach((label, index) => {
    if (label.querySelector('input,select,textarea')) return;
    const input = label.nextElementSibling;
    if (!input?.matches('input:not([type="hidden"]),select,textarea')) return;
    if (!input.id) input.id = `ops-field-${index}`;
    label.htmlFor = input.id;
  });
  workspace.querySelectorAll('form').forEach(form => {
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    form.addEventListener('submit', event => {
      if (event.defaultPrevented) return;
      if (form.dataset.opsSubmitting === 'true') { event.preventDefault(); return; }
      queueMicrotask(() => {
      if (event.defaultPrevented) return;
      form.dataset.opsSubmitting = 'true';
      const button = event.submitter;
      button?.setAttribute('aria-busy', 'true');
      const note = document.createElement('p');
      note.className = 'ops-submit-note';
      note.setAttribute('role', 'status');
      note.textContent = 'جارٍ تنفيذ الإجراء، يرجى الانتظار…';
      form.append(note);
      // Keep submitter name/value intact for existing workflow actions.
      setTimeout(() => {
        delete form.dataset.opsSubmitting;
        button?.removeAttribute('aria-busy');
        note.remove();
      }, 20000);
      });
    });
  });
  window.addEventListener('pageshow', () => {
    workspace.querySelectorAll('[data-ops-submitting]').forEach(form => delete form.dataset.opsSubmitting);
    workspace.querySelectorAll('[aria-busy="true"]').forEach(button => button.removeAttribute('aria-busy'));
    workspace.querySelectorAll('.ops-submit-note').forEach(note => note.remove());
  });
})();
