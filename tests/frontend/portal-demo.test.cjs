const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function load(storage = new Map(), blocked = false) {
  const context = { console, Date, Math, JSON, structuredClone,
    localStorage: { getItem: k => { if (blocked) throw Error('Storage blocked'); return storage.get(k) ?? null; }, setItem: (k, v) => { if (blocked) throw Error('Storage blocked'); storage.set(k, v); } },
    document: { querySelector: () => null }, window: {} };
  vm.runInNewContext(fs.readFileSync('public/js/portal-demo.js', 'utf8'), context);
  return context.window.PortalDemo;
}
const application = { service: 'identity-first-issue', name: 'مواطن تجريبي', nationalId: '1234567890', phone: '777123456', files: [{ name: 'photo.png', type: 'image/png', size: 123 }] };
test('create, persist and prevent duplicate active requests', () => {
  const storage = new Map(); const app = load(storage); app.reset();
  const request = app.create(application);
  assert.match(request.id, /^DEMO-/);
  assert.throws(() => app.create(application), /طلب قائم/);
  assert.ok(load(storage).state.requests.some(r => r.id === request.id));
});
test('validate attachments and required fields', () => {
  const app = load();
  assert.throws(() => app.create({ ...application, name: '' }));
  assert.throws(() => app.create({ ...application, files: [] }));
  assert.throws(() => app.create({ ...application, files: [{ name: 'a.exe', type: 'application/x-msdownload', size: 8 }] }));
  assert.throws(() => app.create({ ...application, files: [{ name: 'a.png', type: 'image/png', size: 6 * 1024 * 1024 }] }));
});
test('appointment availability, cancellation and date checks', () => {
  const app = load(); const a = app.create(application);
  const b = app.create({ ...application, service: 'passports-first-issue' });
  const date = new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10);
  app.book(a.id, date, '09:00', 'صنعاء');
  assert.throws(() => app.book(b.id, date, '09:00', 'صنعاء'), /محجوز/);
  app.cancelAppointment(a.id);
  app.book(b.id, date, '09:00', 'صنعاء');
  assert.throws(() => app.book(a.id, '2020-01-01', '09:00', 'صنعاء'));
});
test('review, payment, issuance and delivery form a guarded lifecycle', () => {
  const app = load(); const r = app.create(application);
  assert.throws(() => app.transition(r.id, 'issued', ''));
  assert.throws(() => app.transition(r.id, 'needs-info', ''), /ملاحظ/);
  app.transition(r.id, 'needs-info', 'صورة أوضح');
  app.resubmit(r.id, [{ name: 'new.jpg', type: 'image/jpeg', size: 123 }]);
  app.transition(r.id, 'approved', '');
  assert.throws(() => app.transition(r.id, 'issued', ''), /الرسوم/);
  app.pay(r.id, 'محفظة تجريبية');
  assert.throws(() => app.pay(r.id, 'محفظة تجريبية'));
  app.transition(r.id, 'issued', '');
  assert.equal(r.status, 'issued');
  assert.ok(r.documentNumber);
  app.transition(r.id, 'completed', 'تم التسليم');
  assert.ok(r.history.length >= 5);
  assert.ok(app.state.notifications.some(n => n.requestId === r.id));
});
test('corrupted or unavailable local storage does not break the demo', () => {
  assert.ok(load(new Map([['eissuance-demo-v1', '{broken']])).state.requests.length);
  const memoryOnly = load(new Map(), true);
  assert.ok(memoryOnly.create(application).id);
  const app = load();
  assert.throws(() => app.transition('missing', 'approved', ''));
});
