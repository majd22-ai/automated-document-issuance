const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

class MockOption {
  constructor(text, value) {
    this.text = text;
    this.value = value;
  }
}

class MockSelect {
  constructor() {
    this.options = [];
    this.value = '';
    this.listeners = {};
  }
  setAttribute() {}
  replaceChildren(...options) {
    this.options = options;
  }
  addEventListener(name, callback) {
    this.listeners[name] = callback;
  }
}

function runBirthDetails(savedBirthPlace = '', field = 'birth_place') {
  const lists = {
    'options-birth_place': ['مستشفى', 'مركز صحي', 'منزل'],
    'options-birth_country': ['اليمن'],
    'options-birth_governorate': [],
    'options-birth_district': [],
    'options-birth_village': [],
  };
  const inputs = ['birth_place', 'birth_country', 'birth_governorate', 'birth_district', 'birth_village'].map(key => {
    const list = lists['options-' + key];
    return {
      id: 'field-' + key,
      value: key === 'birth_place' ? savedBirthPlace : key === 'birth_country' ? 'اليمن' : '',
      hidden: false,
      required: key !== 'birth_village',
      labels: [{textContent: key}],
      attributes: {list: 'options-' + key},
      listeners: {},
      getAttribute(name) { return this.attributes[name] ?? null; },
      setAttribute(name, value) { this.attributes[name] = value; },
      insertAdjacentElement(_, element) { this.select = element; },
      addEventListener(name, callback) { this.listeners[name] = callback; },
      dispatchEvent() {},
      focus() {},
    };
  });
  const listsById = Object.fromEntries(Object.entries(lists).map(([id, values]) => [id, {
    options: values.map(value => ({value})),
    replaceChildren(...options) { this.options = options; },
  }]));
  const inputsById = Object.fromEntries(inputs.map(input => [input.id, input]));
  const root = {
    querySelector(selector) {
      if (selector === '[data-birth-locations]') return {textContent: '[{"name":"تعز","districts":["المعافر","المظفر"]},{"name":"عدن","districts":["المنصورة","خور مكسر"]}]'};
      if (selector.startsWith('label[for="')) return {setAttribute() {}};
      return inputsById[selector.slice(1)] || listsById[selector.slice(1)] || null;
    },
    querySelectorAll(selector) {
      return selector === 'input[list]' ? inputs : [];
    },
  };
  vm.runInNewContext(fs.readFileSync('public/js/birth-details.js', 'utf8'), {
    document: {
      querySelector: () => root,
      createElement: () => new MockSelect(),
    },
    Option: MockOption,
    Event: class {},
    Intl: {DisplayNames: undefined},
  });
  return Object.assign(inputsById['field-' + field], {fields: inputsById});
}

test('birth place dropdown only offers its listed choices, not an enter-custom-name option', () => {
  const input = runBirthDetails();
  assert.deepEqual(input.select.options.map(option => option.value), ['', 'مستشفى', 'مركز صحي', 'منزل']);
  assert.equal(input.select.required, true);

  input.select.value = 'مركز صحي';
  input.select.listeners.change();
  assert.equal(input.value, 'مركز صحي');
  assert.equal(input.hidden, true);
});

test('previously saved custom birth place remains editable without adding a dropdown option', () => {
  const input = runBirthDetails('مرفق خاص');
  assert.equal(input.value, 'مرفق خاص');
  assert.equal(input.hidden, false);
  assert.equal(input.select.value, '');
  assert.equal(input.select.options.some(option => option.value === '__custom'), false);
});

test('birth district offers districts from every governorate before selecting a governorate', () => {
  const input = runBirthDetails('', 'birth_district');
  for (const district of ['المعافر', 'المظفر', 'المنصورة', 'خور مكسر']) {
    assert.ok(input.select.options.some(option => option.value === district));
  }
});

test('selecting birth governorate filters districts and clears the previous selection', () => {
  const input = runBirthDetails('', 'birth_district');
  input.value = 'المنصورة';
  const governorate = input.fields['field-birth_governorate'];
  governorate.value = 'تعز';
  governorate.listeners.input();
  assert.equal(input.value, '');
  assert.ok(input.select.options.some(option => option.value === 'المعافر'));
  assert.equal(input.select.options.some(option => option.value === 'المنصورة'), false);
});
