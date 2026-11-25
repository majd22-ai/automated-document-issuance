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
  const fingerprint = panel.querySelector('[data-ops-fingerprint]');
  const fingerprintContent = fingerprint?.querySelector('[data-ops-fingerprint-content]');
  const fingerprintStatus = fingerprint?.querySelector('[data-ops-fingerprint-status]');
  let fingerprintUrl;
  let fingerprintLoad;
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
    if (name === 'fingerprint') {
      if (!fingerprintUrl) {
        fingerprintUrl = new URL(fingerprint.dataset.opsFingerprint, location.origin);
        const query = new URLSearchParams(location.search);
        for (const key of ['reference', 'page']) {
          if (query.has('fingerprint_' + key)) fingerprintUrl.searchParams.set(key, query.get('fingerprint_' + key));
        }
      }
      loadFingerprint(fingerprintUrl);
    }
    if (focus) {
      const firstControl = view.querySelector('input:not([type=hidden]), button, a');
      (firstControl || heading).focus();
    }
  }

  function close() {
    panel.hidden = true;
    launcher.hidden = false;
    launcher.setAttribute('aria-expanded', 'false');
    opener?.focus();
  }

  function openPanel(button, name) {
    opener = button;
    launcher.hidden = true;
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    showView(name, button !== launcher);
    if (button === launcher) heading.focus();
  }
  document.querySelectorAll('[data-ops-open]').forEach(button => button.addEventListener('click', event => {
    if (button.dataset.opsFingerprintReference && fingerprint) {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      fingerprintUrl = new URL(fingerprint.dataset.opsFingerprint, location.origin);
      fingerprintUrl.searchParams.set('reference', button.dataset.opsFingerprintReference);
    }
    openPanel(button, button.dataset.opsOpen);
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

  function openInitialView() {
    const initialView = new URLSearchParams(location.search).get('tools');
    if (initialView && [...panel.querySelectorAll('[data-ops-view]')].some(view => view.dataset.opsView === initialView)) {
      openPanel(launcher, initialView);
    }
  }

  async function loadFingerprint(url, message = '') {
    receptionUrl = url;
    fingerprintLoad?.abort();
    const controller = new AbortController();
    fingerprintLoad = controller;
    fingerprintUrl = url;
    fingerprintContent.setAttribute('aria-busy', 'true');
    fingerprintStatus.textContent = 'جارٍ تحميل طلبات البصمة…';
    try {
      const response = await fetch(url, {
        headers: { 'X-Operations-Tools': 'fingerprint-reception' },
        redirect: 'error', cache: 'no-store', signal: controller.signal,
      });
      if (!response.ok) throw new Error('Unable to load fingerprint reception');
      const fragment = new DOMParser().parseFromString(await response.text(), 'text/html')
        .querySelector('[data-ops-fingerprint-fragment]');
      if (!fragment) throw new Error('Invalid fingerprint reception');
      if (fingerprintLoad !== controller) return;
      fingerprintContent.replaceChildren(fragment);
      fingerprintStatus.textContent = message;
    } catch (error) {
      if (fingerprintLoad === controller && error.name !== 'AbortError') {
        fingerprintStatus.textContent = (message ? message + ' ' : '') + 'تعذر تحديث قائمة استقبال البصمة.';
      }
    } finally {
      if (fingerprintLoad === controller) fingerprintContent.removeAttribute('aria-busy');
    }
  }

  async function loadFingerprintDevice(url) {
    fingerprintLoad?.abort();
    const controller = new AbortController();
    fingerprintLoad = controller;
    fingerprintUrl = url;
    fingerprintContent.setAttribute('aria-busy', 'true');
    fingerprintStatus.textContent = 'جارٍ تحميل جهاز البصمة…';
    try {
      const response = await fetch(url, {
        headers: { 'X-Operations-Tools': 'fingerprint-device' },
        redirect: 'error', cache: 'no-store', signal: controller.signal,
      });
      if (!response.ok) throw new Error('Unable to load device');
      const fragment = new DOMParser().parseFromString(await response.text(), 'text/html')
        .querySelector('[data-ops-fingerprint-fragment]');
      if (!fragment) throw new Error('Invalid device fragment');
      if (fingerprintLoad !== controller) return;
      fingerprintContent.replaceChildren(fragment);
      fingerprintStatus.textContent = '';
      // تفعيل simulation-modals لعناصر الـ dialog الجديدة داخل اللوحة
      if (typeof window.initSimModals === 'function') window.initSimModals(fingerprintContent);
    } catch (error) {
      if (fingerprintLoad === controller && error.name !== 'AbortError') {
        fingerprintStatus.textContent = 'تعذر تحميل جهاز البصمة.';
      }
    } finally {
      if (fingerprintLoad === controller) fingerprintContent.removeAttribute('aria-busy');
    }
  }

  if (fingerprintContent) {
    let receptionUrl;

    fingerprintContent.addEventListener('click', event => {
      // زر "الانتقال لجهاز البصمة"
      const deviceBtn = event.target.closest('[data-ops-load-device]');
      if (deviceBtn) {
        event.preventDefault();
        loadFingerprintDevice(new URL(deviceBtn.dataset.opsLoadDevice, location.origin));
        return;
      }
      // زر "العودة إلى الاستقبال" من صفحة الجهاز
      const backBtn = event.target.closest('[data-ops-fingerprint-back]');
      if (backBtn) {
        event.preventDefault();
        if (receptionUrl) loadFingerprint(receptionUrl);
        return;
      }
      const link = event.target.closest('a[href]');
      if (!link || link.target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const url = new URL(link.href, location.origin);
      const endpoint = new URL(fingerprint.dataset.opsFingerprint, location.origin);
      if (url.origin !== endpoint.origin || url.pathname !== endpoint.pathname) return;
      event.preventDefault();
      loadFingerprint(url);
    });
    fingerprintContent.addEventListener('submit', async event => {
      const form = event.target;
      if (form.matches('[data-fingerprint-search]')) {
        event.preventDefault();
        const url = new URL(form.action, location.origin);
        url.search = new URLSearchParams(new FormData(form)).toString();
        loadFingerprint(url);
      } else if (form.matches('[data-fingerprint-action]')) {
        event.preventDefault();
        const button = form.querySelector('button[type=submit]');
        if (button.disabled) return;
        const status = form.querySelector('[data-fingerprint-action-status]');
        const currentUrl = fingerprintUrl;
        button.disabled = true;
        status.textContent = 'جارٍ حفظ الإجراء…';
        try {
          const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, redirect: 'error',
          });
          const data = await response.json();
          if (response.ok) {
            status.textContent = data.message;
            if (fingerprintUrl === currentUrl) await loadFingerprint(currentUrl, data.message);
          } else {
            status.textContent = data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'تعذر حفظ الإجراء.';
            button.disabled = false;
          }
        } catch {
          status.textContent = 'تعذر الاتصال بالخادم. حدّث بيانات الطلب قبل إعادة المحاولة.';
          button.disabled = false;
        }
      }
    });
  }

  function renderActivationRecords(container, records) {
    const labels = {
      name: 'الاسم', birth_date: 'تاريخ الميلاد', birth_place: 'مكان الميلاد',
      father_name: 'اسم الأب', father_national_id: 'الرقم الوطني للأب',
      mother_name: 'اسم الأم', mother_national_id: 'الرقم الوطني للأم', family_reference: 'مرجع الأسرة',
    };
    for (const record of records) {
      const card = document.createElement('article');
      card.className = 'card ops-card';
      const title = document.createElement('h3');
      title.textContent = record.fields.name.registry;
      const summary = document.createElement('p');
      summary.textContent = 'رقم سجل الميلاد: ' + record.birth_number;
      const status = document.createElement('p');
      status.className = record.matches_request ? 'alert alert-success' : 'alert alert-info';
      status.textContent = record.matches_request ? 'بيانات الطلب مطابقة للقيد.' : 'توجد بيانات مختلفة أو ناقصة. راجع المقارنة قبل اتخاذ القرار.';
      const wrapper = document.createElement('div');
      wrapper.className = 'ops-table-wrap';
      const table = document.createElement('table');
      table.className = 'table';
      table.setAttribute('aria-label', 'مقارنة بيانات طلب التفعيل بالسجل التجريبي');
      const header = table.createTHead().insertRow();
      for (const label of ['البيان', 'في السجل', 'في طلب التفعيل']) {
        const cell = document.createElement('th');
        cell.scope = 'col';
        cell.textContent = label;
        header.append(cell);
      }
      const body = table.createTBody();
      for (const [key, label] of Object.entries(labels)) {
        const values = record.fields[key];
        const row = body.insertRow();
        const heading = document.createElement('th');
        heading.scope = 'row';
        heading.textContent = label;
        row.append(heading);
        row.insertCell().textContent = values.registry || '—';
        const submitted = row.insertCell();
        submitted.textContent = values.submitted || 'غير مذكور';
        if (values.submitted && values.submitted !== values.registry) {
          submitted.classList.add('activation-comparison-difference');
        }
      }
      wrapper.append(table);
      card.append(title, summary, status, wrapper);
      container.append(card);
    }
  }

  function renderCivilRecords(container, records) {
    const roles = { father: 'أب', mother: 'أم', son: 'ابن', daughter: 'ابنة', child: 'فرد في الأسرة' };
    const statuses = { active: 'سارية', expired: 'منتهية', inactive: 'غير سارية', revoked: 'ملغاة' };
    const element = (tag, value) => {
      const node = document.createElement(tag);
      node.textContent = value;
      return node;
    };
    for (const { person, birth, card, portrait_url, members = [], civilDocuments = [] } of records) {
      const article = document.createElement('article');
      article.className = 'card ops-card';
      const details = document.createElement('dl');
      details.className = 'operations-civil-details';
      for (const [label, value] of [
        ['الرقم الوطني', person.national_id], ['تاريخ الميلاد', person.birth_date], ['مكان الميلاد', person.birth_place],
        ['حالة القيد', Number(person.complete) === 1 ? 'مكتمل' : 'يحتاج مراجعة'], ['مرجع الأسرة', person.family_reference],
        ['الأب', person.father_name], ['الأم', person.mother_name], ['رقم سجل الميلاد', birth?.number],
        ['رقم البطاقة', card?.number], ['حالة البطاقة', statuses[card?.status] ?? card?.status],
      ]) {
        details.append(element('dt', label), element('dd', value || 'غير مسجل'));
      }
      article.append(element('h3', person.name));
      if (portrait_url) {
        const portrait = document.createElement('img');
        portrait.className = 'operations-civil-portrait';
        portrait.alt = 'صورة السجل التجريبي لـ ' + person.name;
        portrait.addEventListener('error', () => portrait.replaceWith(element('p', 'صورة السجل التجريبي غير متوفرة.')));
        portrait.src = portrait_url;
        article.append(portrait);
      }
      article.append(details);
      for (const [label, items] of [
        ['أفراد الأسرة', members.map(member => [member.name, roles[member.family_role]].filter(Boolean).join(' · '))],
        ['الوثائق المدنية التجريبية', civilDocuments.map(doc => [
          doc.document_type, doc.document_number, statuses[doc.status] ?? doc.status, doc.issued_place, doc.issued_at,
        ].filter(Boolean).join(' · '))],
      ]) {
        article.append(element('h4', label));
        if (!items.length) {
          article.append(element('p', 'لا توجد بيانات مسجلة.'));
          continue;
        }
        const list = document.createElement('ul');
        list.append(...items.map(item => element('li', item)));
        article.append(list);
      }
      container.append(article);
    }
  }

  panel.querySelectorAll('[data-ops-civil-check], [data-ops-activation-check]').forEach(civilForm => {
    const result = civilForm.querySelector('[data-ops-civil-outcome]');
    const submit = civilForm.querySelector('button[type=submit]');
    const records = civilForm.closest('section').querySelector('[data-ops-activation-results]');
    const civilRecords = civilForm.closest('section').querySelector('[data-ops-civil-records]');
    const names = civilForm.querySelector('[data-civil-suggestions]');
    const query = names ? civilForm.elements.query : null;
    let suggestionTimer;
    let suggestionRequest;
    function clearNames() {
      clearTimeout(suggestionTimer);
      suggestionRequest?.abort();
      suggestionRequest = null;
      if (names) {
        names.hidden = true;
        names.replaceChildren();
      }
    }
    function suggestCivilNames() {
      clearNames();
      const term = query.value.trim();
      if (term.length < 2 || !/\p{L}/u.test(term)) return;
      suggestionTimer = setTimeout(async () => {
        const controller = new AbortController();
        suggestionRequest = controller;
        const body = new FormData(civilForm);
        body.set('suggest', '1');
        try {
          const response = await fetch(civilForm.action, {
            method: 'POST', body, headers: { Accept: 'application/json' }, redirect: 'error', signal: controller.signal,
          });
          if (!response.ok) return;
          const data = await response.json();
          if (suggestionRequest !== controller || query.value.trim() !== term) return;
          for (const name of data.suggestions ?? []) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = name;
            button.addEventListener('click', () => {
              query.value = name;
              query.focus();
              civilForm.requestSubmit();
            });
            item.append(button);
            names.append(item);
          }
          names.hidden = !names.childElementCount;
        } catch {
          // The explicit lookup is still available.
        }
      }, 250);
    }
    query?.addEventListener('input', event => {
      if (!event.isComposing) suggestCivilNames();
    });
    query?.addEventListener('compositionend', suggestCivilNames);
    const labels = {
      match: 'تطابقت البيانات مع السجل التجريبي.',
      mismatch: 'الاسم المُدخل لا يطابق السجل التجريبي.',
      not_found: 'لم يُعثر على قيد مطابق في السجل التجريبي.',
      unavailable: 'خدمة السجل التجريبي غير متاحة.',
      review: 'عُثر على قيد محتمل، لكن بعض بيانات الطلب تختلف عن السجل. اطلب من المواطن تصحيح البيانات.',
      ambiguous: 'توجد عدة قيود في الأسرة توافق بيانات الميلاد. أدخل رقم سجل الميلاد لتمييز القيد.',
      parent_mismatch: 'تعذر تأكيد أرقام الوالدين ضمن قيد أسرة واحد. راجع الأرقام المقدمة.',
      insufficient: 'يلزم رقم سجل الميلاد أو رقم أحد الوالدين، مع تاريخ ومكان الميلاد.',
      insufficient_name: 'اكتب حرفين على الأقل من اسم المواطن.',
      results: 'قيود الميلاد المطابقة للاسم في هذه الأسرة.',
    };
    civilForm.closest('section').querySelectorAll('[data-ops-civil-number]').forEach(button => button.addEventListener('click', () => {
      query.value = button.dataset.opsCivilNumber;
      civilForm.requestSubmit();
    }));
    civilForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (submit.disabled) return;
      clearNames();
      submit.disabled = true;
      result.textContent = 'جارٍ التحقق…';
      records?.replaceChildren();
      civilRecords?.replaceChildren();
      try {
        const response = await fetch(civilForm.action, {
          method: 'POST', body: new FormData(civilForm), headers: { Accept: 'application/json' }, redirect: 'error',
        });
        const data = await response.json();
        const outcome = data.outcome ?? data.status;
        result.textContent = response.ok
          ? (Object.hasOwn(labels, outcome) ? labels[outcome] : 'تعذر قراءة نتيجة التحقق.')
          : (data.errors ? Object.values(data.errors).flat().join(' ') : 'تعذر إكمال المحاكاة. تحقق من صلاحيتك والجلسة.');
        if (response.ok && typeof data.name === 'string') {
          result.textContent += ' الاسم في السجل: ' + data.name;
        }
        if (response.ok && records && Array.isArray(data.records)) {
          renderActivationRecords(records, data.records);
        }
        if (response.ok && civilRecords && Array.isArray(data.civil_records)) {
          renderCivilRecords(civilRecords, data.civil_records);
        } else if (response.ok && civilRecords && data.fixture) {
          const fixture = document.createElement('p');
          fixture.textContent = data.fixture.name + ' · ' + data.fixture.national_id;
          civilRecords.append(fixture);
        }
      } catch {
        result.textContent = 'تعذر الاتصال بخدمة المحاكاة. أعد المحاولة.';
      } finally {
        submit.disabled = false;
      }
    });
  });

  if (!registry) {
    openInitialView();
    return;
  }
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
  openInitialView();
})();
