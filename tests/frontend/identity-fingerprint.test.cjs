const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function screen(fetch) {
  const listeners = {};
  const form = { action: '/identity-workflow/1/fingerprint', token: 'same-operation-token', addEventListener: (event, fn) => listeners[event] = fn };
  const state = { textContent: '' };
  const result = { children: [], replaceChildren() { this.children = []; }, append(node) { this.children.push(node); } };
  const button = { disabled: false };
  const progress = { value: 0 };
  const root = { classList: { add() {}, remove() {} }, querySelector: selector => ({ '[data-fingerprint-form]': form, '[data-fingerprint-state]': state, '[data-fingerprint-result]': result, '[data-fingerprint-start]': button, '[data-fingerprint-progress]': progress })[selector] };
  class Data { constructor(form) { this.token = form.token; } }
  vm.runInNewContext(fs.readFileSync('public/js/identity-fingerprint-device.js', 'utf8'), {
    document: { querySelector: () => root, createElement: () => ({ textContent: '' }) },
    window: { location: { href: 'http://127.0.0.1:8000/identity-workflow/1/fingerprint', origin: 'http://127.0.0.1:8000' } },
    fetch, FormData: Data, URL,
  });
  return { listeners, form, state, result, button, progress };
}

test('running state waits for the server result and prevents a second concurrent click', async () => {
  let resolve;
  let calls = 0;
  const app = screen(() => { calls++; return new Promise(done => resolve = done); });
  const pending = app.listeners.submit({ preventDefault() {} });
  assert.equal(app.progress.value, 25);
  assert.ok(app.state.textContent.includes('جارٍ'));
  await app.listeners.submit({ preventDefault() {} });
  assert.equal(calls, 1);
  resolve({ ok: true, json: async () => ({ result: 'success', result_label: 'نجحت المحاكاة', source_label: 'محاكاة وليست بصمة حيوية', reference: 'SIM-FP-test', redirect_url: '/identity-workflow/1/fingerprint' }) });
  await pending;
  assert.equal(app.progress.value, 100);
  assert.equal(app.button.disabled, true);
  assert.ok(app.result.children.some(node => node.textContent === 'محاكاة وليست بصمة حيوية'));
});

test('server failure and transport uncertainty never become a successful biometric claim', async () => {
  const sent = [];
  let fail = true;
  const app = screen(async (url, options) => { sent.push(options.body.token); if (fail) throw new Error('network'); return { ok: false, json: async () => ({ errors: { requirements: ['المصادقة ناقصة'] } }) }; });
  await app.listeners.submit({ preventDefault() {} });
  assert.equal(app.button.disabled, false);
  assert.ok(app.state.textContent.includes('تعذر تأكيد'));
  fail = false;
  await app.listeners.submit({ preventDefault() {} });
  assert.deepEqual(sent, ['same-operation-token', 'same-operation-token']);
  assert.ok(app.result.children.some(node => node.textContent === 'المصادقة ناقصة'));
  assert.equal(app.button.disabled, false);
});

test('print and browser PDF controls invoke printing only when enabled', () => {
  let printed = 0;
  const buttons = [false, true].map(disabled => ({ disabled, addEventListener(name, callback) { this.click = callback; } }));
  vm.runInNewContext(fs.readFileSync('public/js/identity-print.js', 'utf8'), { document: { querySelectorAll: () => buttons }, window: { print: () => printed++ } });
  buttons[1].click(); assert.equal(printed, 0);
  buttons[0].click(); assert.equal(printed, 1);
});
