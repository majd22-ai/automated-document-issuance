const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const badgeSource = fs.readFileSync('public/js/notification-count.js', 'utf8');
const workspaceSource = fs.readFileSync('public/js/operations-workspace.js', 'utf8');
const tick = () => new Promise(setImmediate);

test('poll refreshes the existing badge text without changing header appearance', async () => {
  const badge = { textContent: '0', className: 'gov-notification-count', style: 'original' };
  let calls = 0;
  let interval;
  const document = { visibilityState: 'visible', addEventListener() {}, querySelector: selector => selector === '.gov-notification-count' ? badge : { dataset: { countUrl: '/notifications/unread-count' } } };
  vm.runInNewContext(badgeSource, { document, fetch: async (url, options) => {
    calls++;
    assert.equal(url, '/notifications/unread-count');
    assert.equal(options.credentials, 'same-origin');
    return { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ unread: 7 }) };
  }, setInterval: (callback, ms) => { interval = callback; assert.equal(ms, 30000); } });
  await tick();
  assert.equal(badge.textContent, '7');
  assert.equal(badge.className, 'gov-notification-count');
  assert.equal(badge.style, 'original');
  document.visibilityState = 'hidden';
  await interval();
  assert.equal(calls, 1);
});

test('missing header badge causes no background request', () => {
  vm.runInNewContext(badgeSource, { document: { querySelector: () => null }, fetch: () => { throw new Error('Unexpected request'); } });
});

test('failed polling preserves the displayed count', async () => {
  const badge = { textContent: '4' };
  vm.runInNewContext(badgeSource, { document: { visibilityState: 'visible', querySelector: selector => selector === '.gov-notification-count' ? badge : { dataset: { countUrl: '/notifications/unread-count' } }, addEventListener() {} }, fetch: async () => { throw new Error('Offline'); }, setInterval() {} });
  await tick();
  assert.equal(badge.textContent, '4');
});

test('workspace submit feedback respects cancellation and preserves command submitter values', async () => {
  let onSubmit;
  const appended = [];
  const form = { dataset: {}, getAttribute: () => 'POST', addEventListener: (_, callback) => { onSubmit = callback; }, append: value => appended.push(value) };
  const workspace = { querySelectorAll: selector => selector === 'form' ? [form] : [] };
  const button = { value: 'approve', attributes: {}, setAttribute(key, value) { this.attributes[key] = value; }, removeAttribute() {} };
  vm.runInNewContext(workspaceSource, { document: { querySelector: () => workspace, createElement: () => ({ setAttribute() {}, remove() {} }) }, window: { addEventListener() {} }, queueMicrotask, setTimeout() {} });
  const cancelled = { defaultPrevented: false, submitter: button };
  onSubmit(cancelled);
  cancelled.defaultPrevented = true;
  await tick();
  assert.equal(form.dataset.opsSubmitting, undefined);
  onSubmit({ defaultPrevented: false, submitter: button });
  await tick();
  assert.equal(form.dataset.opsSubmitting, 'true');
  assert.equal(button.value, 'approve');
  assert.equal(button.disabled, undefined);
  assert.equal(appended.length, 1);
  let prevented = false;
  onSubmit({ defaultPrevented: false, preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
});
