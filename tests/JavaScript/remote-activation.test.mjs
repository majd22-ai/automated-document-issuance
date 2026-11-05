import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../public/js/remote-activation.js',import.meta.url),'utf8');
const node=(extra={})=>({hidden:false,required:false,disabled:false,events:{},addEventListener(name,fn){this.events[name]=fn;},...extra});
test('identity and local preview dialogs open, preview code can be copied',async()=>{
 let opens=0,copied;
 const modal={showModal(){opens++;}};
 const trigger=node({dataset:{openDialog:'identity'}}),copy=node(),input={value:'123456'};
 const document={querySelectorAll:()=>[trigger],getElementById:()=>modal,querySelector:s=>({'[data-auto-open]':modal,'[data-copy-code]':copy,'[data-local-code]':input}[s]??null)};
 vm.runInNewContext(source,{document,navigator:{clipboard:{writeText:async value=>{copied=value;}}}});
 trigger.events.click();await copy.events.click();assert.equal(opens,2);assert.equal(copied,'123456');
});
test('birth certificate removes only back requirement and camera stops when page hides',async()=>{
 const kind=node({value:'identity_card'}),backInput=node(),birthInput=node();
 const back=node({querySelector:()=>backInput}),birth=node({querySelector:()=>birthInput});
 const video=node(),capture=node(),start=node(),status=node(),file=node();let stopped=0;
 const nodes={'[data-document-kind]':kind,'[data-card-back]':back,'[data-birth-only]':birth,'[data-selfie-video]':video,'[data-capture-selfie]':capture,'[data-start-selfie]':start,'[data-camera-status]':status,'[data-selfie-file]':file};
 const form=node({querySelector:s=>nodes[s]});
 const document=node({hidden:false,querySelectorAll:()=>[],querySelector:s=>s==='[data-remote-evidence]'?form:null});const window=node();
 vm.runInNewContext(source,{document,window,navigator:{mediaDevices:{getUserMedia:async()=>({getTracks:()=>[{stop(){stopped++;}}]})}}});
 assert.equal(backInput.required,true);kind.value='birth_certificate';kind.events.change();assert.equal(back.hidden,true);assert.equal(backInput.disabled,true);assert.equal(birthInput.required,true);
 await start.events.click();assert.equal(video.hidden,false);document.hidden=true;document.events.visibilitychange();assert.equal(stopped,1);assert.equal(video.hidden,true);
});
