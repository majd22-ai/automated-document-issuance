const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('each witness preview is independent and restores its own saved image', () => {
  const cleanups = [], revoked = [];
  const witnesses = [1, 2].map(number => {
    const input = { files: [], addEventListener(name, handler) { this[name] = handler; } };
    const preview = { src: '/private/' + number, hidden: false, getAttribute() { return this.src; }, removeAttribute() {} };
    return {
      input,
      preview,
      querySelector: selector => selector.startsWith('input') ? input : selector === '[data-selfie-preview]' ? preview : null,
      querySelectorAll: () => [],
    };
  });
  let number = 0;
  vm.runInNewContext(fs.readFileSync('public/js/witness-selfies.js', 'utf8'), {
    document: { querySelectorAll: () => witnesses },
    URL: { createObjectURL: () => 'blob:' + ++number, revokeObjectURL: url => revoked.push(url) },
    window: { addEventListener: (_, callback) => cleanups.push(callback) },
  });
  witnesses[0].input.files = [{type: 'image/jpeg'}];
  witnesses[0].input.change();
  assert.equal(witnesses[0].preview.src, 'blob:1');
  assert.equal(witnesses[1].preview.src, '/private/2');
  witnesses[1].input.files = [{type: 'image/png'}];
  witnesses[1].input.change();
  witnesses[0].input.files = [];
  witnesses[0].input.change();
  assert.equal(witnesses[0].preview.src, '/private/1');
  assert.equal(witnesses[1].preview.src, 'blob:2');
  cleanups.forEach(callback => callback());
  assert.deepEqual(revoked, ['blob:1', 'blob:2']);
});

test('witness picker opens the camera or gallery with the matching file input mode', () => {
  const handlers = {};
  const input = {
    attributes: {},
    addEventListener(name, handler) { this[name] = handler; },
    setAttribute(name, value) { this.attributes[name] = value; },
    removeAttribute(name) { delete this.attributes[name]; },
    click() { this.wasClicked = true; },
  };
  const dialog = {
    showModal() { this.isOpen = true; },
    close() { this.isOpen = false; },
  };
  const openButton = { addEventListener: (name, handler) => { handlers.open = handler; } };
  const cancelButton = { addEventListener: (name, handler) => { handlers.cancel = handler; } };
  const cameraButton = { dataset: { selfieSource: 'camera' }, addEventListener: (name, handler) => { handlers.camera = handler; } };
  const galleryButton = { dataset: { selfieSource: 'gallery' }, addEventListener: (name, handler) => { handlers.gallery = handler; } };
  const root = {
    querySelector(selector) {
      return {
        'input[type="file"]': input,
        '[data-selfie-preview]': null,
        dialog,
        '[data-open-selfie-picker]': openButton,
        '[data-close-selfie-picker]': cancelButton,
      }[selector] ?? null;
    },
    querySelectorAll: selector => selector === '[data-selfie-source]' ? [cameraButton, galleryButton] : [],
  };
  vm.runInNewContext(fs.readFileSync('public/js/witness-selfies.js', 'utf8'), {
    document: { querySelectorAll: () => [root] },
    URL: { createObjectURL: () => 'blob:1', revokeObjectURL: () => {} },
    window: { addEventListener: () => {} },
  });

  handlers.open();
  assert.equal(dialog.isOpen, true);
  handlers.camera();
  assert.equal(input.attributes.capture, 'environment');
  assert.equal(input.wasClicked, true);
  assert.equal(dialog.isOpen, false);

  handlers.gallery();
  assert.equal('capture' in input.attributes, false);
  handlers.open();
  handlers.cancel();
  assert.equal(dialog.isOpen, false);
});
