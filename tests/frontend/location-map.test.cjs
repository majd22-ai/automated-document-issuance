const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
test('map sends no external request before intersection and loads once near view', () => {
  const frame = { dataset: { mapSrc: 'https://maps.google.com/maps?q=test' } };
  let callback, disconnected = 0;
  const events = {};
  class Observer {
    constructor(fn, options) { callback = fn; assert.equal(options.rootMargin, '300px'); }
    observe(element) { assert.equal(element, frame); }
    disconnect() { disconnected++; }
  }
  vm.runInNewContext(fs.readFileSync('public/js/location-map.js', 'utf8'), { document: { querySelectorAll: () => [frame] }, IntersectionObserver: Observer, window: { IntersectionObserver: Observer, addEventListener: (key, fn) => events[key] = fn } });
  assert.equal(frame.src, undefined);
  callback([{ isIntersecting: false }]); assert.equal(frame.src, undefined);
  callback([{ isIntersecting: true }]); assert.equal(frame.src, 'https://maps.google.com/maps?q=test');
  assert.equal(frame.dataset.mapSrc, undefined); assert.equal(disconnected, 1);
  events.pagehide(); assert.equal(disconnected, 2);
});
test('map works without IntersectionObserver', () => {
  const frame = { dataset: { mapSrc: 'https://maps.google.com/maps?q=test' } };
  vm.runInNewContext(fs.readFileSync('public/js/location-map.js', 'utf8'), { document: { querySelectorAll: () => [frame] }, window: {} });
  assert.equal(frame.src, 'https://maps.google.com/maps?q=test');
});
