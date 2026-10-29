const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function screen(fetch) {
  const ids = ['activation-checks', 'activation-dialog', 'activation-dialog-title', 'activation-dialog-message', 'activation-feedback', 'activation-finger-controls', 'activation-code-preview', 'activation-code', 'activation-start', 'activation-send', 'activation-otp', 'activation-confirm', 'activation-failure', 'activation-close', 'activation-copy'];
  const nodes = Object.fromEntries(ids.map(id => [id, { listeners: {}, textContent: '', disabled: false, checked: false, value: '',
    addEventListener(event, callback) { this.listeners[event] = callback; }, focus() { this.focused = true; } }]));
  const button = { dataset: { finger: 'right_index' }, addEventListener(event, callback) { this.click = callback; } };
  const root = nodes['activation-checks'];
  root.dataset = { fingerUrl: '/finger', codeUrl: '/code', token: 'visit-token', csrf: 'csrf-token' };
  root.querySelectorAll = () => [button];
  nodes['activation-dialog'].showModal = function () { this.open = true; };
  nodes['activation-dialog'].close = function () { this.open = false; this.listeners.close(); };
  let count = 0, reloaded = false;
  vm.runInNewContext(fs.readFileSync('public/js/activation-visit.js', 'utf8'), {
    document: { getElementById: id => nodes[id] }, URL, fetch,
    crypto: { randomUUID: () => 'operation-' + (++count) },
    window: { location: { href: 'http://localhost/page', origin: 'http://localhost', reload: () => { reloaded = true; } } },
    navigator: { clipboard: { writeText: async () => {} } },
  });
  return { nodes, button, get reloaded() { return reloaded; } };
}

test('finger simulation waits for backend and prevents duplicate requests, then refreshes on close', async () => {
  let resolve, calls = 0;
  const app = screen(() => { calls++; return new Promise(done => { resolve = done; }); });
  app.button.click();
  const start = app.nodes['activation-start'];
  const request = start.listeners.click();
  await start.listeners.click();
  assert.equal(calls, 1);
  assert.equal(start.disabled, true);
  assert.match(app.nodes['activation-dialog-message'].textContent, /جارٍ/);
  resolve({ ok: true, json: async () => ({ result: 'success' }) });
  await request;
  app.nodes['activation-close'].listeners.click();
  assert.equal(app.reloaded, true);
});

test('local OTP is shown as text once and cleared on close; confirmation needs six digits', async () => {
  const app = screen(async () => ({ ok: true, json: async () => ({ source: 'local_trial', local_code: '123456', message: 'رمز تجريبي محلي' }) }));
  await app.nodes['activation-send'].listeners.click();
  assert.equal(app.nodes['activation-code'].textContent, '123456');
  assert.equal(app.nodes['activation-dialog'].open, true);
  app.nodes['activation-close'].listeners.click();
  assert.equal(app.nodes['activation-code'].textContent, '');
  const otp = app.nodes['activation-otp'];
  otp.value = '123'; otp.listeners.input();
  assert.equal(app.nodes['activation-confirm'].disabled, true);
  otp.value = '123456'; otp.listeners.input();
  assert.equal(app.nodes['activation-confirm'].disabled, false);
});

test('transport uncertainty retains the operation key and never claims successful simulation', async () => {
  const sent = [];
  const app = screen(async (url, request) => { sent.push(JSON.parse(request.body)); throw new Error('تعذر الاتصال'); });
  app.button.click();
  await app.nodes['activation-start'].listeners.click();
  await app.nodes['activation-start'].listeners.click();
  assert.equal(sent[0].operation_token, sent[1].operation_token);
  assert.equal(sent[0].visit_token, 'visit-token');
  assert.match(app.nodes['activation-dialog-message'].textContent, /تعذر/);
  assert.equal(app.reloaded, false);
});
