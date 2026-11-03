const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const tick=()=>new Promise(setImmediate);
for(const file of ['notification-count.js','mobile-attestation-simulator.js']) {
 test(`${file}: one timer, no visibility bursts, abort on leave, resume BFCache`,async()=>{
  const events={},windowEvents={},timers=new Map();let next=0,now=0,calls=0,lastSignal;
  const node={dataset:{countUrl:'/count',feedUrl:'/feed'},querySelector:()=>node,addEventListener(){},replaceChildren(){},showModal(){},close(){},append(){}};
  const document={hidden:false,visibilityState:'visible',querySelector:()=>node,addEventListener:(e,f)=>events[e]=f};
  const setInterval=(fn,ms)=>{assert.equal(ms,120000);timers.set(++next,fn);return next;};
  const clearInterval=id=>timers.delete(id);
  const context={document,Date:{now:()=>now},AbortController,URL,setInterval,clearInterval,window:{setInterval,clearInterval,addEventListener:(e,f)=>windowEvents[e]=f,location:{href:'http://localhost/',origin:'http://localhost'}},fetch:async(url,options)=>{calls++;lastSignal=options.signal;return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({unread:0,notifications:[]})};}};
  const source=fs.readFileSync('public/js/'+file,'utf8');
  vm.runInNewContext(source,context);await tick();
  assert.equal(calls,1);assert.equal(timers.size,1);
  vm.runInNewContext(source,context);assert.equal(timers.size,1);assert.equal(calls,1);
  document.hidden=true;document.visibilityState='hidden';events.visibilitychange();
  assert.equal(timers.size,0);assert.equal(lastSignal.aborted,true);
  document.hidden=false;document.visibilityState='visible';events.visibilitychange();await tick();
  assert.equal(calls,1);assert.equal(timers.size,1);
  now=120000;await [...timers.values()][0]();await tick();assert.equal(calls,2);
  windowEvents.pagehide();assert.equal(timers.size,0);assert.equal(lastSignal.aborted,true);
  now=240000;windowEvents.pageshow();await tick();assert.equal(calls,3);assert.equal(timers.size,1);
 });
}
