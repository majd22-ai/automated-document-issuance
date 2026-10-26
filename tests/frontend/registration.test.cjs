const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const helpers = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync('public/js/registration.js', 'utf8'), helpers);
const { validPhone, validPassword } = helpers.module.exports;

test('Yemeni mobile numbers exclude country code, short, long and non-mobile inputs', () => {
  assert.ok(validPhone('777123456'));
  for (const value of ['+967777123456', '123', '0777123456', '123456789', '77712345x']) assert.equal(validPhone(value), false);
});
test('password validation requires letters numbers symbols and at least eight characters', () => {
  assert.ok(validPassword('Strong123!'));
  for (const value of ['abcdefgh', '12345678!', 'Abc12!', 'Strong123', 'Strong   123']) assert.equal(validPassword(value), false);
});
function screen(captchaValid = false) {
  const nodes = new Map();
  function element(id) {
    if (!nodes.has(id)) nodes.set(id, { value: '', textContent: '', disabled: false, required: false, events: {}, validity: '',
      addEventListener(event, fn) { this.events[event] = fn; }, setCustomValidity(message) { this.validity = message; },
      dataset: { captchaCheck: '/register/captcha/check' }, querySelector() { return { value: 'test-csrf' }; },
      showModal() { this.open = true; }, close() { this.open = false; }, focus() {}, reportValidity() { return this.validity === ''; }, checkValidity() { return this.validity === ''; }, requestSubmit() { this.requested = true; },
    });
    return nodes.get(id);
  }
  const context = { fetch: async () => ({ ok: true, json: async () => ({ valid: captchaValid }) }), document: { getElementById: element, querySelectorAll: () => [] }, window: { addEventListener() {}, setInterval() {} } };
  element('resend-code').dataset = { resendAt: '0' };
  element('identity_path').value = 'has_identity';
  vm.runInNewContext(fs.readFileSync('public/js/registration.js', 'utf8'), context);
  return element;
}
test('no-identity dialog keeps only main phone and never invents an ID', () => {
  const node = screen();
  node('no-identity').events.click();
  assert.ok(node('identity-dialog').open);
  node('phone').value = '777123456';
  node('identity-dialog-form').events.submit({ preventDefault() {} });
  assert.equal(node('phone').value, '777123456');
  assert.equal(node('national_id').value, '');
  assert.equal(node('national_id').disabled, true);
  assert.equal(node('identity_path').value, 'no_identity');
  assert.equal(node('identity-dialog').open, false);
});
test('changing main phone uses single field and mismatched password shows Arabic error', () => {
  const node = screen();
  node('phone').value = '777123456'; node('confirmed_phone').value = '771123456';
  node('password').value = 'Strong123!'; node('password_confirmation').value = 'Different123!';
  node('registration-form').events.input();
  assert.equal(node('password-match').textContent, 'كلمتا المرور غير متطابقتين');
});
test('next is clickable and automatically checks captcha before submitting', async () => {
  const node = screen(true);
  assert.equal(node('registration-next').disabled, false);
  node('captcha').value = 'ABC234';
  await node('registration-form').events.submit({ preventDefault() {} });
  assert.equal(node('registration-form').requested, true);
  const failed = screen(false);
  failed('captcha').value = 'ABC234';
  await failed('registration-form').events.submit({ preventDefault() {} });
  assert.equal(failed('registration-form').requested, undefined);
  assert.equal(failed('registration-next').disabled, false);
  assert.ok(failed('registration-feedback').textContent);
});
