const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function simulation(initial) {
  const events = {};
  class Element {
    constructor() { this.children = []; this.listeners = {}; this.textContent = ''; this.open = false; }
    append(node) { this.children.push(node); }
    replaceChildren() { this.children = []; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    showModal() { this.open = true; }
    close() { this.open = false; }
  }
  const nodes = Object.fromEntries(['dialog', '[data-attestation-notifications]', '[data-attestation-count]', '[data-attestation-feed-status]', '[data-attestation-toggle]', '[data-attestation-close]'].map(key => [key, new Element()]));
  const root = { dataset: { feedUrl: '/simulation/identity-attestations/notifications', csrf: 'private-test-token' }, querySelector: selector => nodes[selector] };
  const document = { hidden: false, querySelector: () => root, createElement: () => new Element(), addEventListener: (name, callback) => events[name] = callback };
  let feed = initial;
  let calls = 0;
  const context = { document, URL, window: { location: { href: 'http://127.0.0.1:8000/employee/dashboard', origin: 'http://127.0.0.1:8000' }, setInterval: callback => events.tick = callback }, fetch: async () => { calls++; return { ok: true, json: async () => feed }; } };
  vm.runInNewContext(fs.readFileSync('public/js/mobile-attestation-simulator.js', 'utf8'), context);
  return { nodes, events, document, calls: () => calls, setFeed: value => feed = value };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
const notice = id => ({ id, recipient_name: 'جهة تجريبية', citizen_name: '<script>not HTML</script>', request_reference: 'REQ-test', authority_label: 'جهة العمل', created_at: '2026-10-28T10:00:00Z', read: false, open_url: 'http://127.0.0.1:8000/simulation/identity-attestations/'+id+'/open' });

test('closing hides only, reopening works, same notice stays closed and a new notice opens', async () => {
  const app = simulation({ unread: 1, notifications: [notice(1)] });
  await tick();
  assert.equal(app.nodes.dialog.open, true);
  assert.equal(app.nodes['[data-attestation-count]'].textContent, '1');
  const calls = app.calls();
  app.nodes['[data-attestation-close]'].listeners.click();
  assert.equal(app.nodes.dialog.open, false);
  assert.equal(app.calls(), calls);
  await app.events.tick();
  assert.equal(app.nodes.dialog.open, false);
  app.nodes['[data-attestation-toggle]'].listeners.click();
  assert.equal(app.nodes.dialog.open, true);
  app.nodes['[data-attestation-close]'].listeners.click();
  app.setFeed({ unread: 2, notifications: [notice(2), notice(1)] });
  await app.events.tick();
  assert.equal(app.nodes.dialog.open, true);
});

test('view is a same-origin CSRF POST and notification content is plain text', async () => {
  const app = simulation({ unread: 1, notifications: [notice(1)] });
  await tick();
  const item = app.nodes['[data-attestation-notifications]'].children[0];
  assert.ok(item.children.some(child => child.textContent.includes('<script>not HTML</script>')));
  const form = item.children.find(child => child.method === 'POST');
  assert.equal(form.action, notice(1).open_url);
  assert.equal(form.children[0].name, '_token');
  assert.equal(form.children[0].value, 'private-test-token');
  app.document.hidden = true;
  const calls = app.calls();
  await app.events.tick();
  assert.equal(app.calls(), calls);
});

test('an external URL never receives a CSRF form', async () => {
  const app = simulation({ unread: 1, notifications: [{ ...notice(1), open_url: 'https://example.invalid/collect' }] });
  await tick();
  assert.equal(app.nodes['[data-attestation-notifications]'].children[0].children.some(child => child.method === 'POST'), false);
});

test('rejection dialog opens and closes without submitting a decision', () => {
  const events = {};
  const close = { addEventListener: (name, callback) => events.close = callback };
  const dialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; }, querySelector: () => close };
  const button = { addEventListener: (name, callback) => events.open = callback };
  vm.runInNewContext(fs.readFileSync('public/js/identity-attestation.js', 'utf8'), { document: { querySelector: selector => selector.includes('reject-dialog') ? dialog : button } });
  events.open(); assert.equal(dialog.open, true);
  events.close(); assert.equal(dialog.open, false);
});
