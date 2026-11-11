const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

class MockElement {
  constructor(value = '') {
    this.value = value;
    this.listeners = {};
    this.dataset = {};
    this.disabled = false;
  }
  addEventListener(name, callback) {
    this.listeners[name] = callback;
  }
  querySelectorAll() {
    return [];
  }
}

function setup(response) {
  const type = new MockElement('بطاقة شخصية');
  const number = new MockElement('MOCK-CARD-001');
  const button = new MockElement();
  const status = new MockElement();
  status.textContent = '';
  const fields = Object.fromEntries(['name', 'phone', 'issued_place'].map(key => [key, new MockElement()]));
  const dates = Object.fromEntries(['day', 'month', 'year'].map(key => [key, new MockElement()]));
  const dateFields = new MockElement();
  dateFields.querySelectorAll = () => Object.values(dates);
  dateFields.querySelector = selector => dates[selector.match(/issued_at_(day|month|year)/)[1]];
  const root = new MockElement();
  root.dataset.lookupUrl = '/citizen/identity-card/1/witness-lookup';
  root.querySelector = selector => ({
    'select[name$="[document_type]"]': type,
    '[data-witness-document-number]': number,
    '[data-witness-lookup-button]': button,
    '[data-witness-lookup-status]': status,
    '[data-witness-issued-date]': dateFields,
  })[selector] || null;
  root.querySelectorAll = selector => {
    if (selector === '[data-witness-autofill]') {
      return Object.entries(fields).map(([field, element]) => {
        element.dataset.witnessAutofill = field;
        return element;
      });
    }
    return [];
  };
  vm.runInNewContext(fs.readFileSync('public/js/witness-registry-lookup.js', 'utf8'), {
    document: {
      querySelectorAll: () => [root],
      querySelector: () => ({content: 'csrf-token'}),
    },
    fetch: async (url, options) => {
      assert.equal(url, root.dataset.lookupUrl);
      assert.equal(options.method, 'POST');
      return response;
    },
    AbortController,
    Object,
    Array,
  });
  return {type, number, button, status, fields, dates};
}

test('lookup fills only requested witness fields from a mock registry response', async () => {
  const state = setup({
    ok: true,
    json: async () => ({
      source: 'local_simulation',
      data: {name: 'شاهد تجريبي', phone: '777000111', issued_place: 'مركز تجريبي', issued_at: '2020-03-15'},
    }),
  });

  await state.button.listeners.click();
  assert.equal(state.fields.name.value, 'شاهد تجريبي');
  assert.equal(state.fields.phone.value, '777000111');
  assert.equal(state.fields.issued_place.value, 'مركز تجريبي');
  assert.deepEqual(Object.fromEntries(Object.entries(state.dates).map(([key, value]) => [key, value.value])), {
    day: '15', month: '3', year: '2020',
  });
  assert.match(state.status.textContent, /ليست بيانات سجل حكومي/);

  state.number.value = 'CHANGED';
  state.number.listeners.input();
  assert.equal(state.fields.name.value, '');
  assert.equal(state.fields.phone.value, '');
  assert.equal(state.fields.issued_place.value, '');
  assert.equal(state.dates.day.value, '');
});

test('lookup reports a not-found response without erasing manually populated values', async () => {
  const state = setup({
    ok: false,
    json: async () => ({message: 'لم توجد وثيقة مطابقة في سجلات المحاكاة.'}),
  });
  state.fields.name.value = 'قديمة';
  state.fields.phone.value = '777000111';

  await state.button.listeners.click();
  assert.equal(state.fields.name.value, 'قديمة');
  assert.equal(state.fields.phone.value, '777000111');
  assert.match(state.status.textContent, /لم توجد وثيقة مطابقة/);
});

test('a failed lookup does not erase manually entered witness information', async () => {
  const state = setup({
    ok: false,
    json: async () => ({message: 'لم توجد وثيقة مطابقة في سجلات المحاكاة.'}),
  });
  state.fields.name.value = 'اسم أدخله المستخدم';
  state.fields.phone.value = '777123456';

  await state.button.listeners.click();
  assert.equal(state.fields.name.value, 'اسم أدخله المستخدم');
  assert.equal(state.fields.phone.value, '777123456');
});
