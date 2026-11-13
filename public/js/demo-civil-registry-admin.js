(() => {
  'use strict';

  const root = document.querySelector('[data-demo-admin-search]');
  if (!root) return;
  const form = root.querySelector('[data-demo-admin-search-form]');
  const status = root.querySelector('[data-demo-admin-status]');
  const results = root.querySelector('[data-demo-admin-results]');
  const profile = root.querySelector('[data-demo-admin-profile]');
  let controller;
  let activeQuery = null;
  let currentPage = 1;
  let profileVersion = 0;
  let sectionVersion = 0;
  const label = value => window.demoCivilLabel?.(value) || value;

  const node = (tag, text, className) => {
    const item = document.createElement(tag);
    if (text !== undefined) item.textContent = text;
    if (className) item.className = className;
    return item;
  };

  async function get(url, signal) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'تعذر جلب البيانات.');
    return data;
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    activeQuery = { query: form.elements.q.value.trim(), mode: form.elements.mode.value };
    profileVersion += 1;
    sectionVersion += 1;
    currentPage = 1;
    await searchPage(false);
  });

  async function searchPage(append) {
    if (!activeQuery) return;
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    results.querySelector('[data-demo-admin-more]')?.remove();
    if (!append) results.replaceChildren();
    const url = new URL(root.dataset.searchUrl, window.location.origin);
    url.searchParams.set('q', activeQuery.query);
    url.searchParams.set('mode', activeQuery.mode);
    url.searchParams.set('page', String(currentPage));
    status.textContent = 'جارٍ البحث…';
    if (!append) profile.hidden = true;
    try {
      const data = await get(url, signal);
      if (signal.aborted) return;
      if (!append && !data.results.length) status.textContent = 'لا توجد نتائج مطابقة.';
      else status.textContent = data.has_more ? `عُرضت ${((currentPage - 1) * 20) + data.results.length} نتيجة. يمكنك تحميل المزيد.` : `عدد النتائج المعروضة: ${((currentPage - 1) * 20) + data.results.length}`;
      data.results.forEach(person => {
        const row = node('div', null, 'demo-admin-search-result');
        row.append(node('span', `${person.full_name} · ${person.demo_identifier} · ${person.records_count} سجل`));
        const open = node('button', 'فتح الملف', 'btn btn-outline');
        open.type = 'button';
        open.addEventListener('click', () => showProfile(person.id));
        row.append(open);
        results.append(row);
      });
      if (data.has_more) {
        const more = node('button', 'تحميل المزيد', 'btn btn-outline');
        more.type = 'button';
        more.dataset.demoAdminMore = 'true';
        more.addEventListener('click', async () => {
          currentPage += 1;
          await searchPage(true);
        });
        results.append(more);
      }
    } catch (error) {
      if (error.name !== 'AbortError') status.textContent = error.message || 'تعذر الاتصال بالخادم.';
    }
  }

  async function showProfile(id) {
    const version = ++profileVersion;
    sectionVersion += 1;
    profile.hidden = false;
    profile.replaceChildren(node('p', 'جارٍ تحميل الملف التجريبي…'));
    try {
      const data = await get(root.dataset.personUrl.replace('__DEMO_PERSON__', encodeURIComponent(id)));
      if (version !== profileVersion) return;
      const person = data.person;
      const card = node('article', null, 'demo-admin-search-profile');
      card.append(node('h3', `${person.full_name} · ${person.demo_identifier}`));
      card.append(node('p', `الميلاد: ${person.birth_date}، ${person.birth_place} · الحالة: ${label(person.marital_status)}`));
      person.families.forEach(family => {
        const familyLine = node('div', `الأسرة ${family.demo_identifier} · ${label(family.relationship)}`);
        family.members.forEach(member => {
          const open = node('button', `${member.full_name} (${label(member.relationship)})`, 'demo-registry-back');
          open.type = 'button';
          open.addEventListener('click', () => showProfile(member.id));
          familyLine.append(open);
        });
        card.append(familyLine);
      });
      (person.documents || []).forEach(document => {
        const link = node('a', `${document.label} (${document.demo_identifier})`, 'demo-registry-doc');
        link.href = document.url;
        link.target = '_blank';
        link.rel = 'noopener';
        card.append(link);
      });
      if (person.photo_url) {
        const image = node('img');
        image.src = person.photo_url;
        image.alt = `صورة توضيحية اصطناعية للمواطن ${person.demo_identifier}`;
        image.width = 80;
        card.append(image);
      }
      const sectionNav = node('div');
      const sectionDetails = node('div');
      person.sections.forEach(section => {
        const button = node('button', `${section.label} (${section.count})`, 'demo-registry-section');
        button.type = 'button';
        button.addEventListener('click', async () => {
          const sectionRequest = ++sectionVersion;
          const url = root.dataset.sectionUrl.replace('__DEMO_PERSON__', encodeURIComponent(id)).replace('__DEMO_SECTION__', encodeURIComponent(section.key));
          sectionDetails.replaceChildren(node('p', 'جارٍ التحميل…'));
          try {
            const response = await get(url);
            if (sectionRequest !== sectionVersion) return;
            sectionDetails.replaceChildren();
            if (!response.records.length) sectionDetails.append(node('p', 'لا توجد سجلات في هذا القسم.'));
            response.records.forEach(record => {
              const article = node('article', null, 'demo-registry-record');
              article.append(node('h4', `${record.demo_identifier} · ${label(record.status)}`));
              article.append(node('p', `${record.occurred_on || ''} ${record.place || ''}`));
              Object.entries(record.details || {}).forEach(([key, value]) => {
                if (value !== null && typeof value !== 'object') article.append(node('p', `${label(key)}: ${label(String(value))}`));
              });
              record.documents.forEach(document => {
                const link = node('a', document.label, 'demo-registry-doc');
                link.href = document.url;
                link.target = '_blank';
                link.rel = 'noopener';
                article.append(link);
              });
              sectionDetails.append(article);
            });
          } catch (error) {
            if (sectionRequest !== sectionVersion) return;
            sectionDetails.replaceChildren(node('p', error.message || 'تعذر تحميل القسم.'));
          }
        });
        sectionNav.append(button);
      });
      card.append(sectionNav, sectionDetails);
      profile.replaceChildren(card);
    } catch (error) {
      if (version !== profileVersion) return;
      profile.replaceChildren(node('p', error.message || 'تعذر تحميل الملف.'));
    }
  }
})();
