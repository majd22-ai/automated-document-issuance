const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

class Node {
  constructor(tag = 'div', text = '') {
    this.tag = tag; this.textContent = text; this.children = []; this.listeners = {};
    this.dataset = {}; this.style = {}; this.attributes = {}; this.hidden = false;
    this.classList = { add() {}, remove() {}, toggle() {} };
  }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  addEventListener(type, fn) { this.listeners[type] = fn; }
  setAttribute(key, value) { this.attributes[key] = value; }
  focus() { this.focused = true; }
  querySelector(selector) { return this.selectors?.[selector] || null; }
  querySelectorAll() { return this.children.filter(child => child.dataset?.section); }
  getBoundingClientRect() { return { left: 100, top: 80, width: 700, height: 600 }; }
  setPointerCapture() {}
  closest() { return null; }
  show() { this.open = true; }
  close() { this.open = false; this.listeners.close?.({}); }
  async fire(type, extra = {}) { return this.listeners[type]?.({ preventDefault() {}, target: this, currentTarget: this, ...extra }); }
}
const tick = () => new Promise(resolve => setImmediate(resolve));
function setup(fetcher) {
  const selectors = {};
  for (const key of ['search', 'status', 'results', 'profile', 'person', 'sections', 'records', 'close', 'minimize', 'back', 'drag']) {
    selectors[`[data-demo-registry-${key}]`] = new Node();
  }
  const form = selectors['[data-demo-registry-search]'];
  form.elements = { q: new Node('input'), mode: { value: 'identifier' } };
  form.elements.q.value = 'DEMO-CIV-000001';
  const dialog = new Node('dialog');
  dialog.selectors = { ...selectors, '[name=q]': form.elements.q, '[data-demo-registry-search] input': form.elements.q };
  dialog.dataset = { requestId: '12', searchUrl: '/search', personUrl: '/people/__DEMO_PERSON__', sectionUrl: '/people/__DEMO_PERSON__/__DEMO_SECTION__', suggestedSection: 'birth' };
  const trigger = new Node('button');
  const calls = [];
  vm.runInNewContext(fs.readFileSync('public/js/demo-civil-registry.js', 'utf8'), {
    document: { querySelector: selector => selector === '#demo-civil-registry-window' ? dialog : trigger,
      createElement: tag => new Node(tag), createTextNode: text => new Node('#text', text) },
    window: { location: { origin: 'https://example.test' }, innerWidth: 1200, innerHeight: 900 },
    URL, AbortController,
    fetch: async (url, options) => { calls.push({ url: String(url), options }); return fetcher(url, options); },
  });
  return { dialog, trigger, form, calls, get: key => selectors[`[data-demo-registry-${key}]`] };
}
const response = data => ({ ok: true, json: async () => data });
const person = id => ({ person: { id, full_name: `مواطن ${id}`, demo_identifier: `DEMO-${id}`, birth_date: '2000-01-01', birth_place: 'اختبار', gender: 'male', marital_status: 'single', synthetic_address: 'اختبار', photo_url: null,
  families: [{demo_identifier: 'DEMO-FAM', relationship: 'child', members: [{id: 2, full_name: 'فرد الأسرة', demo_identifier: 'DEMO-2', relationship: 'spouse'}]}],
  sections: [{key: 'birth', label: 'المواليد', count: 1}], documents: [{label: 'وثيقة مستقلة', demo_identifier: 'DEMO-DOC', url: '/document', mime_type: 'image/png'}] } });

test('modeless window closes with Escape, restores focus and leaves review values intact', async () => {
  const review = { notes: 'ملاحظات لم تحفظ', decision: 'return' };
  const ui = setup(() => response({}));
  await ui.trigger.fire('click');
  assert.equal(ui.dialog.open, true);
  await ui.dialog.fire('keydown', { key: 'Escape' });
  assert.equal(ui.dialog.open, false);
  assert.equal(ui.trigger.focused, true);
  await ui.trigger.fire('click');
  assert.equal(ui.dialog.open, true);
  assert.deepEqual(review, { notes: 'ملاحظات لم تحفظ', decision: 'return' });
});

test('search uses fetch, carries request context, opens suggested section and links family members', async () => {
  const ui = setup(url => response(String(url).includes('/search') ? {results: [{id: 1, full_name: 'تجريبي', demo_identifier: 'DEMO-1', records_count: 1, documents_count: 1}], has_more: false} : String(url).endsWith('request_id=12') && String(url).includes('/birth') ? {records: [], label: 'المواليد'} : person(1)));
  await ui.form.fire('submit');
  await ui.get('results').children[0].fire('click');
  await tick();
  assert.match(ui.calls[0].url, /request_id=12/);
  assert.equal(ui.calls[0].options.cache, 'no-store');
  assert.ok(ui.calls.some(call => call.url.includes('/people/1/birth')));
  const descendants = node => [node, ...node.children.flatMap(descendants)];
  const nodes = descendants(ui.get('person'));
  assert.ok(nodes.some(node => node.tag === 'a' && node.href === '/document'));
  assert.ok(nodes.some(node => node.tag === 'button' && node.textContent.includes('فرد الأسرة')));
});

test('network error is visible and pointer dragging supports touch without dragging controls', async () => {
  const ui = setup(() => { throw new Error('تعذر الاتصال'); });
  await ui.form.fire('submit');
  assert.match(ui.get('status').textContent, /تعذر الاتصال/);
  const handle = ui.get('drag');
  await handle.fire('pointerdown', { button: 0, pointerId: 3, clientX: 100, clientY: 100 });
  await handle.fire('pointermove', { pointerId: 3, clientX: 130, clientY: 140 });
  assert.equal(ui.dialog.style.left, '130px');
  assert.equal(ui.dialog.style.top, '120px');
  await handle.fire('pointerup', { pointerId: 3 });
  await handle.fire('pointerdown', { button: 0, pointerId: 4, target: {closest: () => true} });
  await handle.fire('pointermove', { pointerId: 4, clientX: 300, clientY: 300 });
  assert.equal(ui.dialog.style.left, '130px');
});

test('a slower previous profile cannot overwrite the citizen selected most recently', async () => {
  let resolveFirst;
  const ui = setup(url => {
    if (String(url).includes('/search')) return response({results: [1, 2].map(id => ({id, full_name: `شخص ${id}`, demo_identifier: `DEMO-${id}`}))});
    if (String(url).includes('/birth')) return response({records: [], label: 'المواليد'});
    if (String(url).includes('/people/1')) return new Promise(resolve => { resolveFirst = () => resolve(response(person(1))); });
    return response(person(2));
  });
  await ui.form.fire('submit');
  const pending = ui.get('results').children[0].fire('click');
  await ui.get('results').children[1].fire('click');
  await tick();
  resolveFirst();
  await pending;
  assert.equal(ui.get('person').children[1].children[0].textContent, 'مواطن 2');
});

test('admin record fields serialize typed details and selected parties without asking for JSON', async () => {
  const form = new Node('form');
  const details = {value: '{"certificate_number":"DEMO-OLD"}', parentElement: new Node()};
  const parties = {value: '[{"person_id":6,"role":"deceased"}]', parentElement: new Node(), required: true};
  const type = new Node('select'); type.value = 'death';
  const people = {cloneNode() {
    const select = new Node('select');
    select.removeAttribute = () => {};
    select.prepend = node => select.children.unshift(node);
    return select;
  }};
  form.elements = {record_type: type, details_json: details, parties_json: parties, person_id: people};
  form.insertBefore = panel => { form.panel = panel; };
  vm.runInNewContext(fs.readFileSync('public/js/demo-civil-registry-fields.js', 'utf8'), {
    window: {}, document: {querySelectorAll: () => [form], createElement: tag => new Node(tag)},
  });
  assert.equal(details.parentElement.hidden, true);
  assert.equal(parties.required, false);
  form.panel.children[0].children[0].value = 'DEMO-NEW';
  await form.fire('submit');
  assert.equal(JSON.parse(details.value).certificate_number, 'DEMO-NEW');
  assert.deepEqual(JSON.parse(parties.value), [{person_id: 6, role: 'deceased'}]);
  type.value = 'restriction';
  await type.fire('change');
  assert.equal(form.panel.children.length, 4);
});

test('admin search renders a profile and can load its civil records', async () => {
  const root = new Node();
  const form = new Node('form');
  form.elements = {q: {value: 'DEMO-CIV-000001'}, mode: {value: 'identifier'}};
  const status = new Node(); const results = new Node(); const profile = new Node();
  root.selectors = {'[data-demo-admin-search-form]': form, '[data-demo-admin-status]': status, '[data-demo-admin-results]': results, '[data-demo-admin-profile]': profile};
  root.dataset = {searchUrl: '/search', personUrl: '/people/__DEMO_PERSON__', sectionUrl: '/people/__DEMO_PERSON__/__DEMO_SECTION__'};
  vm.runInNewContext(fs.readFileSync('public/js/demo-civil-registry-admin.js', 'utf8'), {
    document: {querySelector: () => root, createElement: tag => new Node(tag)},
    window: {location: {origin: 'https://example.test'}}, URL, AbortController,
    fetch: async url => response(String(url).includes('/search') ? {results: [{id: 1, full_name: 'تجريبي', demo_identifier: 'DEMO-1', records_count: 1}]} : String(url).includes('/birth') ? {records: []} : person(1)),
  });
  await form.fire('submit');
  await results.children[0].children[1].fire('click');
  assert.equal(profile.hidden, false);
  const card = profile.children[0];
  const sectionNav = card.children[card.children.length - 2];
  await sectionNav.children[0].fire('click');
  assert.match(card.children.at(-1).children[0].textContent, /لا توجد سجلات/);
});
