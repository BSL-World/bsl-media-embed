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
 const ids=['bsl-library','bsl-folder-picker','bsl-select-folder','bsl-up','bsl-picker-error','bsl-picker-path','bsl-picker-folders','bsl-picker-boundary','bsl-browse','bsl-site-home','bsl-root-choice','bsl-root-selected','bsl-root-location','bsl-close-picker'];
 const nodes=Object.fromEntries(ids.map(id=>[id,new Element()]));nodes['bsl-library'].dataset.browseError='Cannot read folder';
 Object.assign(nodes['bsl-library'].dataset,{siteLabel:'Within site',neighbourLabel:'Beside site',containerLabel:'Site and neighbouring folders'});
 const requests=[];
 const context={URL,URLSearchParams,location:{href:'https://site.test/administrator/index.php'},document:{getElementById:id=>nodes[id]||null,createElement:()=>new Element()},fetch:url=>new Promise(resolve=>requests.push({url,resolve}))};
 vm.runInNewContext(readFileSync(new URL('../com_bslmedia/media/library.js',import.meta.url),'utf8'),context);
 return {nodes,requests};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const folder={location:{area:'neighbour',parts:['media']},boundaryArea:'container',relative:'media',value:'../media',folders:[{name:'denied',relative:'media/denied'}]};
function answer(request,data,ok=true){request.resolve({ok,json:async()=>data});}
test('root chooser preserves last valid navigation on permission failure and saves a relative value',async()=>{
 const {nodes:n,requests}=setup();n['bsl-browse'].click();
 assert.equal(requests[0].url.searchParams.get('initial'),'library');
 answer(requests[0],folder);await tick();assert.equal(n['bsl-picker-boundary'].textContent,'Site and neighbouring folders');
 n['bsl-picker-folders'].children[0].children[0].click();
 assert.equal(n['bsl-picker-path'].textContent,'Beside site → media');
 answer(requests[1],{error:'Permission denied'},false);await tick();
 assert.equal(n['bsl-picker-path'].textContent,'Beside site → media');assert.equal(n['bsl-picker-folders'].children.length,1);
 assert.equal(n['bsl-up'].disabled,false);assert.equal(n['bsl-select-folder'].disabled,false);
 assert.equal(n['bsl-root-choice'].value,'original');
 n['bsl-picker-folders'].children[0].children[0].click();answer(requests[2],{});await tick();
 assert.equal(n['bsl-picker-path'].textContent,'Beside site → media');assert.equal(n['bsl-picker-folders'].children.length,1);
 n['bsl-picker-folders'].children[0].children[0].click();answer(requests[3],null);await tick();
 assert.equal(n['bsl-picker-error'].textContent,'Cannot read folder');
 n['bsl-select-folder'].click();assert.equal(n['bsl-root-choice'].value,'media');assert.equal(n['bsl-root-selected'].value,'1');assert.equal(n['bsl-root-location'].textContent,'Beside site → media');assert.equal(n['bsl-folder-picker'].open,false);
});
test('return to site overrides a stale request and up stops at the server boundary',async()=>{
 const {nodes:n,requests}=setup();n['bsl-browse'].click();
 n['bsl-site-home'].click();assert.equal(requests[1].url.searchParams.get('initial'),'site');
 answer(requests[1],{...folder,location:{area:'site',parts:[]},relative:'site',value:'.',folders:[]});await tick();
 answer(requests[0],{error:'Old failure'},false);await tick();
 assert.equal(n['bsl-picker-path'].textContent,'Within site');assert.equal(n['bsl-picker-error'].textContent,'');
 n['bsl-up'].click();assert.equal(requests[2].url.searchParams.get('relative'),'');
 answer(requests[2],{...folder,location:{area:'container',parts:[]},relative:'',value:'..',folders:[]});await tick();
 assert.equal(n['bsl-up'].disabled,true);n['bsl-select-folder'].click();assert.equal(n['bsl-root-choice'].value,'');assert.equal(n['bsl-root-location'].textContent,'Site and neighbouring folders');
});
test('a site-only boundary is described without server paths or a neighbouring-folder promise',async()=>{
 const {nodes:n,requests}=setup();n['bsl-browse'].click();
 answer(requests[0],{...folder,location:{area:'site',parts:[]},boundaryArea:'site',relative:'',value:'.',folders:[]});await tick();
 assert.equal(n['bsl-picker-path'].textContent,'Within site');assert.equal(n['bsl-picker-boundary'].textContent,'Within site');
 assert.equal(n['bsl-up'].disabled,true);n['bsl-select-folder'].click();assert.equal(n['bsl-root-location'].textContent,'Within site');
});
