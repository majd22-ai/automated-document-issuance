const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('invalid field opens its collapsed sections without opening unrelated settings', () => {
  let handler;
  const form = {addEventListener(name,callback,capture){assert.equal(name,'invalid');assert.equal(capture,true);handler=callback;}};
  const outer = {tagName:'DETAILS',open:false,parentElement:form};
  const inner = {tagName:'DETAILS',open:false,parentElement:outer};
  const wrapper = {tagName:'DIV',parentElement:inner};
  const unrelated = {tagName:'DETAILS',open:false,parentElement:form};
  vm.runInNewContext(fs.readFileSync('public/js/service-settings.js','utf8'),{document:{querySelector:()=>form}});
  handler({target:{parentElement:wrapper}});
  assert.equal(outer.open,true);
  assert.equal(inner.open,true);
  assert.equal(unrelated.open,false);
});
