const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function screen(fetch, overrides = {}) {
  let now = 0, sequence = 0;
  const timers = new Map();
  class Element {
    constructor(dataset = {}) { this.dataset = dataset; this.listeners = {}; this.children = {}; this.textContent = ''; this.value = ''; this.disabled = false; this.style = {setProperty(){},removeProperty(){}}; this.classes = new Set(); this.classList = { add: x=>this.classes.add(x),remove:x=>this.classes.delete(x),toggle:(x,on)=>on?this.classes.add(x):this.classes.delete(x) }; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelector(key) { return this.children[key] ??= new Element(); }
    showModal() { this.open = true; }
    close() { this.open = false; this.listeners.close?.(); }
    focus() { this.focused = true; }
    setAttribute(key,value) { this[key] = value; }
    removeAttribute(key) { delete this[key]; }
    hasAttribute() { return false; }
    reset() { this.wasReset = true; Object.values(this.children).forEach(child => child.value = ''); }
  }
  const root = new Element({prepareUrl:'/prepare',fingerUrl:'/finger',codeUrl:'/code',verifyUrl:'/verify',token:'',rightToken:'server-right',leftToken:'server-left',rightPassed:'0',leftPassed:'0',hasCode:'0',csrf:'csrf', ...overrides});
  const fingers = ['right_index','left_index'].map(finger=>new Element({finger}));
  const steps = ['identity','fingers','verify','final'].map(activationStep=>new Element({activationStep}));
  root.querySelectorAll = selector => selector === '[data-finger]' ? fingers : selector === '[data-activation-step]' ? steps : [];
  const document = {querySelector:()=>root,createRange:()=>({selectNodeContents(){}}),addEventListener(){}};
  const context = {document,URL,fetch,Date:class extends Date {static now(){return now;}},setInterval:callback=>{const id=++sequence;timers.set(id,callback);return id;},clearInterval:id=>timers.delete(id),FormData: class { *[Symbol.iterator]() { yield ['identity_type','national_id'];yield ['identity_number','1122334455'];yield ['phone','777123456']; } },
    window:{addEventListener(){},location:{href:'http://192.168.8.199:8000/page',origin:'http://192.168.8.199:8000'},getSelection:()=>({removeAllRanges(){},addRange(){}})},navigator:{}};
  // No crypto.randomUUID and no clipboard: this is an HTTP LAN/mobile context.
  vm.runInNewContext(fs.readFileSync('public/js/activation-visit.js','utf8'),context);
  return {root,fingers,steps,get:key=>root.querySelector(key),async advance(ms){const end=now+ms;while(now<end){now=Math.min(end,now+100);[...timers.values()].forEach(fn=>fn());}await new Promise(setImmediate);}};
}
const ok = result => ({ok:true,json:async()=>result});
const submit = async element => element.listeners.submit({preventDefault(){}});
const click = async element => element.listeners.click({currentTarget:element,preventDefault(){}});
async function prepare(app) { await submit(app.get('[data-activation-identity]')); }
const down = box => box.listeners.pointerdown({button:0,pointerId:1,preventDefault(){}});
async function hold(app, box) {down(box);await app.advance(6000);box.listeners.pointerup();}
const identity = {visit_token:'server-visit',right_token:'right-key',left_token:'left-key',phone:'+967777123456',registry:{message:'مطابقة سجل الحساب المحلي'}};

test('one button opens wizard on HTTP LAN without randomUUID; finger requests wait for server and use server tokens', async()=>{
  let resolve; const sent=[];
  const app=screen(async(url,request)=>{sent.push(JSON.parse(request.body));if(String(url).endsWith('/prepare'))return ok(identity);return new Promise(done=>resolve=done);});
  await click(app.get('[data-activation-open]'));
  assert.equal(app.get('[data-activation-dialog]').open,true);
  await prepare(app);
  assert.equal(app.fingers[1].disabled,true);
  down(app.fingers[0]);
  await app.advance(5900);
  assert.equal(sent.length,1);
  assert.equal(app.fingers[0].querySelector('[data-finger-check]').hidden,true);
  await app.advance(100);
  await click(app.fingers[0]);
  assert.equal(sent.length,2);
  assert.equal(sent[1].operation_token,'right-key');
  assert.equal(sent[1].visit_token,'server-visit');
  assert.equal(app.fingers[1].disabled,true);
  resolve(ok({result:'success'}));await app.advance(0);
  assert.equal(app.fingers[0].disabled,true);
  assert.equal(app.fingers[1].disabled,false);
});

test('both fingers precede message; close message, verify OTP, then enable final activation', async()=>{
  const app=screen(async(url)=>ok(String(url).endsWith('/prepare')?identity:String(url).endsWith('/finger')?{result:'success'}:String(url).endsWith('/code')?{source:'local_trial',local_code:'123456',message:'رمز تجريبي'}:{verified:true,message:'تم التحقق'}));
  await prepare(app);await hold(app,app.fingers[0]);await hold(app,app.fingers[1]);
  assert.equal(app.get('[data-activation-send]').disabled,false);
  await click(app.get('[data-activation-send]'));
  assert.equal(app.get('[data-activation-message]').open,true);
  assert.equal(app.get('[data-message-code]').textContent,'123456');
  await click(app.get('[data-message-copy]'));
  assert.match(app.get('[data-message-status]').textContent,/النسخ التلقائي غير متاح/);
  assert.equal(app.get('#activation-confirm').disabled,true);
  await click(app.get('[data-message-close]'));
  const otp=app.get('[data-activation-verify]').querySelector('[name="otp"]');
  assert.equal(otp.focused,true);otp.value='١٢٣٤٥٦';
  await submit(app.get('[data-activation-verify]'));
  assert.equal(app.get('#activation-confirm').disabled,false);
  assert.equal(app.get('[data-activation-final]').querySelector('[name="otp"]').value,'123456');
});

test('transport failure retains operation key; bad OTP never enables activation', async()=>{
  const sent=[];
  const app=screen(async(url,request)=>{if(String(url).endsWith('/prepare'))return ok(identity);sent.push(JSON.parse(request.body));throw new Error('تعذر الاتصال');});
  await prepare(app);await hold(app,app.fingers[0]);await hold(app,app.fingers[0]);
  assert.equal(sent[0].operation_token,sent[1].operation_token);
  assert.equal(app.get('[data-activation-send]').disabled,true);
  const otp=app.get('[data-activation-verify]').querySelector('[name="otp"]');otp.value='123456';
  await submit(app.get('[data-activation-verify]'));
  assert.equal(app.get('#activation-confirm').disabled,true);
});

test('opening never skips manual identity entry even when a previous visit has both fingers and an OTP',async()=>{
  let calls=0;
  const app=screen(async()=>{calls++;return ok(identity);},{token:'old-visit',rightPassed:'1',leftPassed:'1',hasCode:'1'});
  await click(app.get('[data-activation-open]'));
  assert.equal(app.steps[0].hidden,false);
  assert.equal(app.steps[1].hidden,true);
  assert.equal(app.get('[data-activation-identity]').wasReset,true);
  assert.equal(app.fingers[0].disabled,true);
  assert.equal(calls,0);
});

test('release at five seconds cancels without backend request; a fresh full hold is required',async()=>{
  let captures=0;
  const app=screen(async url=>{if(String(url).endsWith('/prepare'))return ok(identity);captures++;return ok({result:'success'});});
  await prepare(app);down(app.fingers[0]);await app.advance(5000);app.fingers[0].listeners.pointerup();
  await app.advance(6000);
  assert.equal(captures,0);
  assert.equal(app.fingers[0].querySelector('[data-finger-check]').hidden,true);
  await hold(app,app.fingers[0]);
  assert.equal(captures,1);
  assert.equal(app.fingers[0].querySelector('[data-finger-check]').hidden,false);
});

test('invalid identity or phone response cannot open the fingerprint step',async()=>{
  const app=screen(async()=>({ok:false,json:async()=>({errors:{identity_number:['الرقم الوطني غير صحيح']}})}));
  await click(app.get('[data-activation-open]'));
  await prepare(app);
  assert.equal(app.steps[0].hidden,false);
  assert.equal(app.steps[1].hidden,true);
  assert.equal(app.fingers[0].disabled,true);
});
