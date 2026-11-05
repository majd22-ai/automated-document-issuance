import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('registration disables hidden identity fields and switches to family fields', () => {
  let selected = 'has_identity';
  const handlers = {};
  const cardInput = {disabled:false}; const familyInput = {disabled:false};
  const card = {hidden:false,querySelectorAll:()=>[cardInput]};
  const family = {hidden:false,querySelectorAll:()=>[familyInput]};
  const form = {querySelector:()=>({value:selected}), querySelectorAll:s=>s==='[data-card-fields]'?[card]:[family], addEventListener:(k,f)=>handlers[k]=f};
  const doc = {querySelector:s=>s==='[data-linked-register]'?form:null, querySelectorAll:()=>[], addEventListener:()=>{}};
  vm.runInNewContext(readFileSync('public/js/linked-registration.js','utf8'), {document:doc,window:{addEventListener:()=>{}},navigator:{}});
  assert.equal(cardInput.disabled,false); assert.equal(familyInput.disabled,true);
  selected='no_identity'; handlers.change();
  assert.equal(card.hidden,true); assert.equal(cardInput.disabled,true); assert.equal(family.hidden,false); assert.equal(familyInput.disabled,false);
});
