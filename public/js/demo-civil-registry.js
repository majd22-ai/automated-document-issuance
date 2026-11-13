(() => {
  'use strict';

  const dialog = document.querySelector('#demo-civil-registry-window');
  const trigger = document.querySelector('[data-demo-registry-open]');
  if (!dialog || !trigger) return;

  const form = dialog.querySelector('[data-demo-registry-search]');
  const status = dialog.querySelector('[data-demo-registry-status]');
  const results = dialog.querySelector('[data-demo-registry-results]');
  const profile = dialog.querySelector('[data-demo-registry-profile]');
  const personPanel = dialog.querySelector('[data-demo-registry-person]');
  const sections = dialog.querySelector('[data-demo-registry-sections]');
  const records = dialog.querySelector('[data-demo-registry-records]');
  const requestId = dialog.dataset.requestId;
  const label = value => window.demoCivilLabel?.(value) || value;
  let profileVersion = 0;
  let sectionVersion = 0;
  let activePersonId = null;
  let searchController = null;
  let activeSearch = null;
  let currentPage = 1;
  let minimized = false;

  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text !== undefined && text !== null) node.textContent = text;
    if (className) node.className = className;
    return node;
  };

  function setStatus(message, state = '') {
    status.textContent = message;
    status.dataset.state = state;
  }

  function requestUrl(url) {
    const target = new URL(url, window.location.origin);
    if (requestId) target.searchParams.set('request_id', requestId);
    return target;
  }

  async function getJson(url, signal) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'تعذر إكمال الطلب.';
      throw new Error(message);
    }
    return payload;
  }

  trigger.addEventListener('click', () => {
    if (!dialog.open) dialog.show();
    if (minimized) {
      minimized = false;
      dialog.classList.remove('is-minimized');
      const minimizeButton = dialog.querySelector('[data-demo-registry-minimize]');
      minimizeButton.setAttribute('aria-label', 'تصغير النافذة');
      minimizeButton.title = 'تصغير';
    }
    dialog.querySelector('[name=q]').focus();
  });
  dialog.querySelector('[data-demo-registry-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => trigger.focus());
  // Non-modal dialogs need an explicit Escape handler in browsers without close requests.
  dialog.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      dialog.close();
    }
  });
  dialog.querySelector('[data-demo-registry-minimize]').addEventListener('click', event => {
    minimized = !minimized;
    dialog.classList.toggle('is-minimized', minimized);
    event.currentTarget.setAttribute('aria-label', minimized ? 'استعادة النافذة' : 'تصغير النافذة');
    event.currentTarget.title = minimized ? 'استعادة' : 'تصغير';
    if (!minimized) dialog.querySelector('[data-demo-registry-search] input').focus();
  });
  dialog.querySelector('[data-demo-registry-back]').addEventListener('click', () => {
    profileVersion += 1;
    sectionVersion += 1;
    profile.hidden = true;
    results.hidden = false;
    dialog.querySelector('[data-demo-registry-search] input').focus();
  });
  dialog.addEventListener('cancel', event => {
    event.preventDefault();
    dialog.close();
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const query = form.elements.q.value.trim();
    if (query.length < 2) {
      setStatus('أدخل حرفين على الأقل للبحث.', 'error');
      form.elements.q.focus();
      return;
    }
    activeSearch = { query, mode: form.elements.mode.value };
    profileVersion += 1;
    sectionVersion += 1;
    currentPage = 1;
    profile.hidden = true;
    results.hidden = false;
    await loadSearchPage(false);
  });

  async function loadSearchPage(append) {
    if (!activeSearch) return;
    searchController?.abort();
    searchController = new AbortController();
    const signal = searchController.signal;
    results.querySelector('[data-demo-registry-more]')?.remove();
    if (!append) results.replaceChildren();
    setStatus('جارٍ البحث في السجلات التجريبية…', 'busy');
    const url = new URL(dialog.dataset.searchUrl, window.location.origin);
    url.searchParams.set('q', activeSearch.query);
    url.searchParams.set('mode', activeSearch.mode);
    url.searchParams.set('page', String(currentPage));
    if (requestId) url.searchParams.set('request_id', requestId);
    try {
      const payload = await getJson(url, signal);
      if (signal.aborted) return;
      const pageResults = payload.results || [];
      if (!append && pageResults.length === 0) {
        setStatus('لا توجد نتائج مطابقة في السجل التجريبي.');
        return;
      }
      pageResults.forEach(result => {
        const button = element('button', null, 'demo-registry-result');
        button.type = 'button';
        const info = element('span');
        info.append(element('strong', result.full_name));
        info.append(element('small', `سجلات: ${result.records_count} · وثائق: ${result.documents_count}`));
        button.append(info, element('span', result.demo_identifier, 'demo-registry-result-id'));
        button.addEventListener('click', () => openPerson(result.id));
        results.append(button);
      });
      const shown = (currentPage - 1) * 20 + pageResults.length;
      setStatus(payload.has_more ? `عُرضت ${shown} نتيجة. يمكنك تحميل المزيد.` : `عدد النتائج المعروضة: ${shown}`);
      if (payload.has_more) {
        const more = element('button', 'تحميل المزيد من النتائج', 'demo-registry-back');
        more.type = 'button';
        more.dataset.demoRegistryMore = 'true';
        more.addEventListener('click', async () => {
          currentPage += 1;
          await loadSearchPage(true);
        });
        results.append(more);
      }
    } catch (error) {
      if (error.name !== 'AbortError') setStatus(error.message || 'تعذر الاتصال بالخادم.', 'error');
    }
  }

  async function openPerson(personId) {
    const version = ++profileVersion;
    sectionVersion += 1;
    activePersonId = personId;
    results.hidden = true;
    profile.hidden = false;
    personPanel.replaceChildren();
    sections.replaceChildren();
    records.replaceChildren();
    setStatus('جارٍ تحميل ملف المواطن التجريبي…', 'busy');
    const url = dialog.dataset.personUrl.replace('__DEMO_PERSON__', encodeURIComponent(personId));
    try {
      const payload = await getJson(requestUrl(url));
      if (version !== profileVersion) return;
      const person = payload.person;
      const summary = element('div');
      summary.append(element('h3', person.full_name));
      summary.append(element('p', person.demo_identifier));
      summary.append(element('p', `تاريخ الميلاد: ${person.birth_date} · مكان الميلاد: ${person.birth_place}`));
      summary.append(element('p', `الجنس: ${label(person.gender)} · الحالة الاجتماعية: ${label(person.marital_status)}`));
      summary.append(element('p', `العنوان: ${person.synthetic_address}`));
      const image = element('img');
      image.alt = `صورة توضيحية اصطناعية للمواطن ${person.demo_identifier}`;
      image.src = person.photo_url || '';
      image.hidden = !person.photo_url;
      personPanel.append(image, summary);
      const familySummary = element('div', null, 'demo-registry-families');
      familySummary.append(element('strong', 'الأسرة'));
      if (!person.families.length) familySummary.append(element('span', ' · لا توجد علاقة أسرية مسجلة.'));
      person.families.forEach(family => {
        const list = element('ul');
        family.members.forEach(member => {
          const row = element('li');
          const open = element('button', `${member.full_name} (${label(member.relationship)}) · ${member.demo_identifier}`, 'demo-registry-back');
          open.type = 'button';
          open.addEventListener('click', () => openPerson(member.id));
          row.append(open);
          list.append(row);
        });
        familySummary.append(element('p', `${family.demo_identifier} · صلة المواطن: ${label(family.relationship)}`), list);
      });
      personPanel.append(familySummary);
      (person.documents || []).forEach(document => {
        const link = element('a', `${document.label} (${document.demo_identifier})`, 'demo-registry-doc');
        link.href = document.url;
        link.target = '_blank';
        link.rel = 'noopener';
        personPanel.append(link);
      });
      person.sections.forEach(section => {
        const button = element('button', null, 'demo-registry-section');
        button.type = 'button';
        button.dataset.section = section.key;
        button.append(document.createTextNode(`${section.label} `));
        button.append(element('span', String(section.count)));
        button.addEventListener('click', () => openSection(section.key));
        sections.append(button);
      });
      setStatus('اختر قسماً لعرض الوقائع المرتبطة.');
      const suggested = person.sections.find(section => section.key === dialog.dataset.suggestedSection && section.count > 0);
      await openSection((suggested || person.sections.find(section => section.count > 0) || person.sections[0]).key);
    } catch (error) {
      if (version !== profileVersion) return;
      setStatus(error.message || 'تعذر تحميل ملف المواطن.', 'error');
    }
  }

  async function openSection(section) {
    if (!activePersonId) return;
    const version = ++sectionVersion;
    sections.querySelectorAll('[data-section]').forEach(button => button.setAttribute('aria-current', String(button.dataset.section === section)));
    records.replaceChildren();
    setStatus('جارٍ تحميل القسم…', 'busy');
    const url = dialog.dataset.sectionUrl.replace('__DEMO_PERSON__', encodeURIComponent(activePersonId)).replace('__DEMO_SECTION__', encodeURIComponent(section));
    try {
      const payload = await getJson(requestUrl(url));
      if (version !== sectionVersion) return;
      if (!payload.records.length) {
        records.append(element('p', 'لا توجد وقائع في هذا القسم لهذا المواطن.'));
      }
      payload.records.forEach(record => renderRecord(record));
      setStatus(payload.records.length ? `${payload.label}: ${payload.records.length} سجل` : payload.label);
    } catch (error) {
      if (version !== sectionVersion) return;
      setStatus(error.message || 'تعذر تحميل السجل.', 'error');
    }
  }

  function renderRecord(record) {
    const card = element('article', null, 'demo-registry-record');
    card.append(element('h4', `${record.demo_identifier} · ${label(record.status)}`));
    const list = element('dl');
    if (record.occurred_on) addDetail(list, 'التاريخ', record.occurred_on);
    if (record.place) addDetail(list, 'المكان', record.place);
    Object.entries(record.details || {}).forEach(([key, value]) => {
      if (value !== null && value !== undefined && typeof value !== 'object') addDetail(list, label(key), label(String(value)));
    });
    record.parties.forEach(party => addDetail(list, label(party.role), `${party.full_name || 'غير متاح'} · ${party.demo_identifier || ''}`));
    card.append(list);
    record.documents.forEach(document => {
      const link = element('a', `${document.label} (${document.demo_identifier})`, 'demo-registry-doc');
      link.href = document.url;
      link.rel = 'noopener';
      link.target = '_blank';
      if (document.mime_type === 'application/pdf') link.download = '';
      card.append(link);
    });
    records.append(card);
  }

  function addDetail(list, label, value) {
    const group = element('div');
    group.append(element('dt', label), element('dd', value));
    list.append(group);
  }

  const dragHandle = dialog.querySelector('[data-demo-registry-drag]');
  let drag = null;
  dragHandle.addEventListener('pointerdown', event => {
    if (event.button !== 0 || event.target.closest('button,input,select,textarea,a,[data-no-drag]')) return;
    const rect = dialog.getBoundingClientRect();
    drag = { id: event.pointerId, x: event.clientX, y: event.clientY, left: rect.left, top: rect.top };
    dialog.style.left = `${rect.left}px`;
    dialog.style.top = `${rect.top}px`;
    dialog.style.right = 'auto';
    dragHandle.setPointerCapture(event.pointerId);
  });
  dragHandle.addEventListener('pointermove', event => {
    if (!drag || drag.id !== event.pointerId) return;
    const rect = dialog.getBoundingClientRect();
    const left = Math.min(Math.max(8, drag.left + event.clientX - drag.x), Math.max(8, window.innerWidth - rect.width - 8));
    const top = Math.min(Math.max(8, drag.top + event.clientY - drag.y), window.innerHeight - Math.min(rect.height, 60));
    dialog.style.left = `${left}px`;
    dialog.style.top = `${top}px`;
  });
  const stopDrag = event => {
    if (drag?.id === event.pointerId) drag = null;
  };
  dragHandle.addEventListener('pointerup', stopDrag);
  dragHandle.addEventListener('pointercancel', stopDrag);
})();
