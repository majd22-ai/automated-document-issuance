const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/local-password-recovery.js', 'utf8');

function setup(copy) {
  const events = {};
  const closeButton = { addEventListener: (_, callback) => { events.closeClick = callback; } };
  const copyButton = { addEventListener: (_, callback) => { events.copyClick = callback; } };
  const status = { textContent: '' };
  const input = { value: '123456', focused: false, selected: false, focus() { this.focused = true; }, select() { this.selected = true; } };
  const codeField = { focused: false, focus() { this.focused = true; } };
  const modal = {
    open: true,
    close() { this.open = false; events.closeEvent?.(); },
    showModal() { this.open = true; },
    addEventListener: (_, callback) => { events.closeEvent = callback; },
    querySelector: (selector) => ({ '[data-recovery-close]': closeButton, '[data-recovery-copy]': copyButton, '[data-recovery-copy-status]': status })[selector],
  };
  const document = { getElementById: (id) => ({ 'recovery-preview': modal, 'local-recovery-copy-code': input, 'recovery-code': codeField })[id] };
  vm.runInNewContext(source, { document, navigator: { clipboard: { writeText: copy } } });
  return { events, modal, input, codeField, status };
}

test('copy sends the displayed code to clipboard and X focuses verification field', async () => {
  let copied;
  const ui = setup(async (value) => { copied = value; });
  assert.equal(ui.modal.open, true);
  await ui.events.copyClick();
  assert.equal(copied, '123456');
  assert.ok(ui.status.textContent.length > 0);
  ui.events.closeClick();
  assert.equal(ui.modal.open, false);
  assert.equal(ui.codeField.focused, true);
});

test('clipboard denial selects code for manual copying', async () => {
  const ui = setup(async () => { throw new Error('Denied'); });
  await ui.events.copyClick();
  assert.equal(ui.input.selected, true);
  assert.equal(ui.input.focused, true);
  assert.match(ui.status.textContent, /Ctrl/);
});

test('page without a preview does not need clipboard access', () => {
  assert.doesNotThrow(() => vm.runInNewContext(source, { document: { getElementById: () => null } }));
});
