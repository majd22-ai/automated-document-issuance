const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('photo preview replaces object URLs and restores the saved private image when selection clears', () => {
  const events = {};
  const removed = [];
  const input = { files: [], addEventListener: (name, handler) => events[name] = handler };
  const preview = { src: '/private-photo', hidden: false, getAttribute: () => '/private-photo', removeAttribute() {} };
  let count = 0;
  vm.runInNewContext(fs.readFileSync('public/js/identity-card-wizard.js', 'utf8'), {
    document: { querySelector: selector => selector === '[data-identity-photo]' ? input : preview },
    URL: { createObjectURL: () => 'blob:'+ ++count, revokeObjectURL: value => removed.push(value) },
    window: { addEventListener: (name, handler) => events[name] = handler },
  });
  input.files = [{ type: 'image/png' }];
  events.change();
  assert.equal(preview.src, 'blob:1');
  input.files = [{ type: 'image/jpeg' }];
  events.change();
  assert.deepEqual(removed, ['blob:1']);
  events.pagehide();
  assert.deepEqual(removed, ['blob:1', 'blob:2']);
  input.files = [];
  events.change();
  assert.equal(preview.src, '/private-photo');
  assert.equal(preview.hidden, false);
});

test('other platform pages need no photo preview elements', () => {
  vm.runInNewContext(fs.readFileSync('public/js/identity-card-wizard.js', 'utf8'), { document: { querySelector: () => null } });
});
