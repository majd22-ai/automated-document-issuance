(() => {
  const panel = document.querySelector('#operations-tools');
  if (!panel) return;

  const launcher = document.querySelector('.operations-tools-launcher');
  const heading = panel.querySelector('h2');
  const handle = panel.querySelector('[data-ops-resize]');
  const sizeButton = panel.querySelector('[data-ops-size]');
  const backButton = panel.querySelector('[data-ops-back]');
  const registry = panel.querySelector('[data-ops-registry]');
  const content = registry?.querySelector('[data-ops-registry-content]');
  const status = registry?.querySelector('[data-ops-status]');
  const mobile = matchMedia('(max-width:640px)');
  let opener;
  let widthMode = 'compact';
  let drag;
  let topic = 'directory';
  let pending;

  function setWidth(mode, pixels = 0) {
    widthMode = mode;
    const full = window.innerWidth;
    const min = Math.min(350, full);
    const wanted = mode === 'full' ? full : mode === 'half' ? full / 2 : mode === 'compact' ? 410 : pixels;
    const width = mobile.matches ? full : Math.round(Math.min(full, Math.max(min, wanted)));
    panel.style.setProperty('--ops-tools-width', width + 'px');
    handle.setAttribute('aria-valuemin', String(Math.round(min / full * 100)));
    handle.setAttribute('aria-valuenow', String(Math.round(width / full * 100)));
    sizeButton.textContent = width === full ? 'نصف الشاشة' : 'ملء الشاشة';
  }

  function showView(name, focus = true) {
    const views = [...panel.querySelectorAll('[data-ops-view]')];
    const view = views.find(item => item.dataset.opsView === name) || views[0];
    name = view.dataset.opsView;
    views.forEach(item => { item.hidden = item !== view; });
    backButton.hidden = name === 'home';
    setWidth(name === 'home' ? 'compact' : 'half');
    if (name === 'mock' && !content.firstElementChild && topic !== 'notifications' && !pending) {
      loadRegistry(registry.dataset.opsRegistry);
    }
    if (focus) {
      const firstControl = view.querySelector(name === 'demo' ? '[name=q]' : 'button, a');
      (firstControl || heading).focus();
    }
  }

  function close() {
    panel.hidden = true;
    launcher.hidden = false;
    launcher.setAttribute('aria-expanded', 'false');
    opener?.focus();
  }

  document.querySelectorAll('[data-ops-open]').forEach(button => button.addEventListener('click', () => {
    opener = button;
    launcher.hidden = true;
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    showView(button.dataset.opsOpen, button !== launcher);
    if (button === launcher) heading.focus();
  }));

  panel.querySelectorAll('[data-ops-view-button]').forEach(button => button.addEventListener('click', () => showView(button.dataset.opsViewButton)));
  backButton.addEventListener('click', () => showView('home'));
  panel.querySelector('[data-ops-close]').addEventListener('click', close);
  panel.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !event.defaultPrevented) {
      event.preventDefault();
      close();
    }
  });
  sizeButton.addEventListener('click', () => setWidth(panel.getBoundingClientRect().width >= window.innerWidth - 1 ? 'half' : 'full'));
  window.addEventListener('resize', () => setWidth(widthMode, panel.getBoundingClientRect().width));
  handle.addEventListener('pointerdown', event => {
    if (event.button !== 0 || drag) return;
    drag = { id: event.pointerId, offset: panel.getBoundingClientRect().right - event.clientX };
    handle.setPointerCapture(event.pointerId);
  });
  handle.addEventListener('pointermove', event => {
    if (drag?.id === event.pointerId) setWidth('custom', event.clientX + drag.offset);
  });
  handle.addEventListener('lostpointercapture', () => { drag = null; });
  handle.addEventListener('keydown', event => {
    const width = panel.getBoundingClientRect().width;
    switch (event.key) {
      case 'ArrowLeft': setWidth('custom', width - 25); break;
      case 'ArrowRight': setWidth('custom', width + 25); break;
      case 'Home': setWidth('half'); break;
      case 'End': setWidth('full'); break;
      default: return;
    }
    event.preventDefault();
  });

  if (!registry) return;
  const buttons = [...registry.querySelectorAll('[data-ops-section]')];
  const notifications = registry.querySelector('[data-ops-notifications]');
  const base = new URL(registry.dataset.opsRegistry, location.origin);
  let suggestionTimer;
  let suggestionRequest;

  function clearSuggestions() {
    clearTimeout(suggestionTimer);
    suggestionRequest?.abort();
    suggestionRequest = null;
    const list = content.querySelector('[data-ops-suggestions]');
    if (list) {
      list.hidden = true;
      list.replaceChildren();
      content.querySelector('[data-ops-suggestion-status]').textContent = '';
    }
  }

  function suggestNames(form) {
    clearSuggestions();
    const name = form.elements.q.value.trim();
    if (name.length < 2) return;
    suggestionTimer = setTimeout(async () => {
      const controller = new AbortController();
      suggestionRequest = controller;
      const target = new URL(base);
      target.search = new URLSearchParams(new FormData(form)).toString();
      const list = form.querySelector('[data-ops-suggestions]');
      const message = form.querySelector('[data-ops-suggestion-status]');
      try {
        const response = await fetch(target, {
          headers: { 'X-Operations-Tools': 'registry-suggestions', Accept: 'application/json' },
          cache: 'no-store', redirect: 'error', signal: controller.signal,
        });
        if (!response.ok) throw new Error('Suggestions unavailable');
        const matches = await response.json();
        if (suggestionRequest !== controller || !form.isConnected) return;
        for (const person of matches) {
          const item = document.createElement('li');
          const link = document.createElement('a');
          const profile = new URL(base);
          profile.pathname += '/' + encodeURIComponent(person.id);
          link.href = profile;
          link.textContent = person.name;
          item.append(link);
          list.append(item);
        }
        list.hidden = matches.length === 0;
        message.textContent = matches.length ? 'الأسماء المقترحة: ' + matches.length : 'لا توجد أسماء مطابقة لما كتبته.';
      } catch (error) {
        if (suggestionRequest === controller && error.name !== 'AbortError') {
          message.textContent = 'تعذر تحميل الاقتراحات. يمكنك استخدام زر البحث.';
        }
      }
    }, 250);
  }

  function updateTopic() {
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.opsSection === topic)));
    content.hidden = topic === 'notifications';
    notifications.hidden = topic !== 'notifications';
    if (content.firstElementChild?.dataset.opsFragment === 'directory' && !content.hidden) {
      status.textContent = topic === 'directory' ? '' : 'اختر مواطنًا لعرض هذا القسم.';
    }
    content.querySelectorAll('[data-registry-topic]').forEach(section => {
      section.hidden = topic !== 'directory' && section.dataset.registryTopic !== topic;
    });
  }

  async function loadRegistry(url, focus = false) {
    clearSuggestions();
    pending?.abort();
    const controller = new AbortController();
    pending = controller;
    const target = new URL(url, location.origin);
    content.setAttribute('aria-busy', 'true');
    status.textContent = 'جارٍ تحميل السجل…';
    try {
      const response = await fetch(target, {
        headers: { 'X-Operations-Tools': 'registry' },
        credentials: 'same-origin',
        redirect: 'error',
        cache: 'no-store',
        signal: controller.signal,
      });
      if (!response.ok) throw new Error('Registry unavailable');
      const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
      const fragment = documentFragment.querySelector('[data-ops-fragment]');
      if (!fragment) throw new Error('Invalid registry response');
      if (pending !== controller) return;
      content.replaceChildren(fragment);
      status.textContent = '';
      updateTopic();
      if (focus && !panel.hidden && !registry.hidden && !content.hidden) {
        const title = content.querySelector('h1');
        title?.setAttribute('tabindex', '-1');
        title?.focus();
      }
    } catch (error) {
      if (pending === controller && error.name !== 'AbortError') {
        status.textContent = 'تعذر تحميل السجل. تحقق من توفر النظام وصلاحية الجلسة ثم أعد المحاولة.';
      }
    } finally {
      if (pending === controller) {
        pending = null;
        content.removeAttribute('aria-busy');
      }
    }
  }

  buttons.forEach(button => button.addEventListener('click', () => {
    clearSuggestions();
    topic = button.dataset.opsSection;
    status.textContent = '';
    updateTopic();
    if (topic !== 'notifications' && (!content.firstElementChild || (['directory', 'civil'].includes(topic) && content.firstElementChild.dataset.opsFragment !== 'directory'))) {
      loadRegistry(base);
    }
    if (topic === 'civil' && content.querySelector('[name=q]')) {
      content.querySelector('[name=q]').focus();
    }
  }));
  content.addEventListener('input', event => {
    if (event.target.matches('input[name=q]') && !event.isComposing) suggestNames(event.target.form);
  });
  content.addEventListener('compositionend', event => {
    if (event.target.matches('input[name=q]')) suggestNames(event.target.form);
  });
  content.addEventListener('change', event => {
    if (event.target.matches('select')) suggestNames(event.target.form);
  });
  content.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    const target = new URL(link.href, location.origin);
    if (target.origin !== base.origin || (target.pathname !== base.pathname && !target.pathname.startsWith(base.pathname + '/'))) return;
    event.preventDefault();
    loadRegistry(target, true);
  });
  content.addEventListener('submit', event => {
    if (!event.target.matches('form[role=search]')) return;
    event.preventDefault();
    const target = new URL(event.target.action, location.origin);
    target.search = new URLSearchParams(new FormData(event.target)).toString();
    loadRegistry(target);
  });
  updateTopic();
})();
