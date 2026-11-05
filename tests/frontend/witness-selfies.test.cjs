const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('each witness preview is independent and restores its own saved image', () => {
  const cleanups = [], revoked = [];
  const witnesses = [1, 2].map(number => {
    const input = { files: [], addEventListener(name, handler) { this[name] = handler; } };
    const preview = { src: '/private/' + number, hidden: false, getAttribute() { return this.src; }, removeAttribute() {} };
    return { input, preview, querySelector: selector => selector.startsWith('input') ? input : preview };
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
