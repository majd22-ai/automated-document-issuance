import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../public/js/operations-workspace.js',import.meta.url),'utf8');
test('mobile work menu collapses, opens with button, and remains visible on desktop',()=>{
 const menu={hidden:false},events={},attributes={};let resize;
 const button={setAttribute:(key,value)=>attributes[key]=value,addEventListener:(key,fn)=>events[key]=fn};
 const workspace={querySelectorAll:()=>[]};
 const document={querySelector:selector=>selector==='.ops-content'?workspace:button,getElementById:()=>menu};
 const window={matchMedia:()=>({matches:true,addEventListener:(key,fn)=>resize=fn}),addEventListener:()=>{}};
 vm.runInNewContext(source,{document,window});
 assert.equal(menu.hidden,true);assert.equal(attributes['aria-expanded'],'false');events.click();assert.equal(menu.hidden,false);assert.equal(attributes['aria-expanded'],'true');resize({matches:false});assert.equal(menu.hidden,false);resize({matches:true});assert.equal(menu.hidden,true);
});
