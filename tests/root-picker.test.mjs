import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
class Element{
 constructor(){this.children=[];this.events={};this.dataset={};this.disabled=false;this.value='original';this.textContent='';}
 addEventListener(type,fn){this.events[type]=fn;}
 click(){this.events.click?.();}
 append(node){this.children.push(node);}
 replaceChildren(){this.children=[];}
 querySelectorAll(){return [];}
 showModal(){this.open=true;}close(){this.open=false;}
}
function setup(){
 const ids=['bsl-library','bsl-folder-picker','bsl-select-folder','bsl-up','bsl-picker-error','bsl-picker-path','bsl-picker-folders','bsl-picker-boundary','bsl-browse','bsl-site-home','bsl-root','bsl-close-picker'];
 const nodes=Object.fromEntries(ids.map(id=>[id,new Element()]));nodes['bsl-library'].dataset.browseError='Cannot read folder';
 const requests=[];
 const context={URL,URLSearchParams,location:{href:'https://site.test/administrator/index.php'},document:{getElementById:id=>nodes[id]||null,createElement:()=>new Element()},fetch:url=>new Promise(resolve=>requests.push({url,resolve}))};
 vm.runInNewContext(readFileSync(new URL('../com_bslmedia/media/library.js',import.meta.url),'utf8'),context);
 return {nodes,requests};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const folder={path:'/home/account/media',relative:'media',value:'../media',boundary:'/home/account',folders:[{name:'denied',relative:'media/denied'}]};
function answer(request,data,ok=true){request.resolve({ok,json:async()=>data});}
test('root chooser preserves last valid navigation on permission failure and saves a relative value',async()=>{
 const {nodes:n,requests}=setup();n['bsl-browse'].click();
 assert.equal(requests[0].url.searchParams.get('initial'),'library');
 answer(requests[0],folder);await tick();assert.equal(n['bsl-picker-boundary'].textContent,'/home/account');
 n['bsl-picker-folders'].children[0].children[0].click();
 assert.equal(n['bsl-picker-path'].textContent,'/home/account/media');
 answer(requests[1],{error:'Permission denied'},false);await tick();
 assert.equal(n['bsl-picker-path'].textContent,'/home/account/media');assert.equal(n['bsl-picker-folders'].children.length,1);
 assert.equal(n['bsl-up'].disabled,false);assert.equal(n['bsl-select-folder'].disabled,false);
 assert.equal(n['bsl-root'].value,'original');
 n['bsl-picker-folders'].children[0].children[0].click();answer(requests[2],{});await tick();
 assert.equal(n['bsl-picker-path'].textContent,'/home/account/media');assert.equal(n['bsl-picker-folders'].children.length,1);
 n['bsl-picker-folders'].children[0].children[0].click();answer(requests[3],null);await tick();
 assert.equal(n['bsl-picker-error'].textContent,'Cannot read folder');
 n['bsl-select-folder'].click();assert.equal(n['bsl-root'].value,'../media');assert.equal(n['bsl-folder-picker'].open,false);
});
test('return to site overrides a stale request and up stops at the server boundary',async()=>{
 const {nodes:n,requests}=setup();n['bsl-browse'].click();
 n['bsl-site-home'].click();assert.equal(requests[1].url.searchParams.get('initial'),'site');
 answer(requests[1],{...folder,path:'/home/account/site',relative:'site',value:'.',folders:[]});await tick();
 answer(requests[0],{error:'Old failure'},false);await tick();
 assert.equal(n['bsl-picker-path'].textContent,'/home/account/site');assert.equal(n['bsl-picker-error'].textContent,'');
 n['bsl-up'].click();assert.equal(requests[2].url.searchParams.get('relative'),'');
 answer(requests[2],{...folder,path:'/home/account',relative:'',value:'..',folders:[]});await tick();
 assert.equal(n['bsl-up'].disabled,true);n['bsl-select-folder'].click();assert.equal(n['bsl-root'].value,'..');
});
