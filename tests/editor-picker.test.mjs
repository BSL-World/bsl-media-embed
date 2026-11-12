import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
class Element {
  constructor(tag){this.tag=tag;this.children=[];this.events={};this.style={};this.attrs={};this.value='';}
  append(...nodes){this.children.push(...nodes);for(const n of nodes)n.parent=this;if(this.tag==='select'&&this.children.length)this.value ||= this.children[0].value;}
  replaceChildren(...nodes){this.children=[];this.value='';this.append(...nodes);}
  setAttribute(k,v){this.attrs[k]=v;}
  addEventListener(k,fn){(this.events[k]??=[]).push(fn);}
  fire(k){for(const fn of this.events[k]??[])fn({preventDefault(){}});}
  querySelectorAll(tag){return this.children.flatMap(n=>[...(n.tag===tag?[n]:[]),...n.querySelectorAll(tag)]);}
  focus(){} showModal(){} close(){this.fire('close');} remove(){if(this.parent)this.parent.children=this.parent.children.filter(n=>n!==this);}
}
const pickerSource=readFileSync(new URL('../plg_editors-xtd_bslmedia/media/library-picker.js',import.meta.url),'utf8').replace('export function','function');
function setup(){
 const document={createElement:tag=>new Element(tag),body:new Element('body'),querySelector:()=>null};
 const requests=[]; const context=vm.createContext({document,URL,AbortController,setTimeout,clearTimeout,
  window:{location:{href:'https://site.test/administrator/',origin:'https://site.test'}},
  fetch:(url,options)=>new Promise(resolve=>requests.push({url,options,resolve}))});
 vm.runInContext(pickerSource,context);
 vm.runInContext(readFileSync(new URL('../plg_editors-xtd_bslmedia/media/formatting.js',import.meta.url),'utf8').replace('export function','function'),context);
 return {context,document,requests};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const item={name:'Урок.mp3',folder:'Section',type:'mp3',shortcode:'{bslaudio file="media-abc"}'};
const page={items:[item],folders:['','Section'],page:1,pages:2,total:31};
test('picker searches, filters and pages; selects canonical shortcode as text',async()=>{
 const {context,requests}=setup();let chosen,message;
 const picker=context.createLibraryPicker({url:'/index.php?task=library.files',text:k=>k,select:v=>chosen=v,status:v=>message=v});
 const pending=picker.load();requests[0].resolve({ok:true,json:async()=>page});await pending;
 const [search,folder,type,list,navigation]=picker.pane.children;
 assert.equal(list.children[0].textContent,'Section/Урок.mp3 · MP3');
 list.children[0].fire('click');assert.equal(chosen.shortcode,item.shortcode);
 folder.value='Section';type.value='mp3';search.value='Урок';folder.fire('change');
 assert.equal(chosen,null);assert.equal(requests[1].url.searchParams.get('q'),'Урок');
 assert.equal(requests[1].url.searchParams.get('folder'),'Section');assert.equal(requests[1].url.searchParams.get('type'),'mp3');
 requests[1].resolve({ok:true,json:async()=>page});await tick();navigation.children[2].fire('click');
 assert.equal(requests[2].url.searchParams.get('page'),'2');picker.stop();assert.equal(message,'LOADING');
});
test('stale responses after switching panes cannot overwrite a provider selection; failures disable insertion',async()=>{
 const {context,requests}=setup();let chosen='provider',message;
 const picker=context.createLibraryPicker({url:'/index.php',text:k=>k,select:v=>chosen=v,status:v=>message=v});
 const first=picker.load();picker.pause();chosen='provider';requests[0].resolve({ok:true,json:async()=>page});await first;
 assert.equal(chosen,'provider');assert.equal(picker.pane.children[3].children.length,0);
 const second=picker.load();requests[1].resolve({ok:false});await second;assert.equal(chosen,null);assert.equal(message,'LOAD_ERROR');
 const unsafe=context.createLibraryPicker({url:'https://other.test/',text:k=>k,select(){},status:v=>message=v});
 await unsafe.load();assert.equal(requests.length,2);assert.equal(message,'LOAD_ERROR');picker.stop();unsafe.stop();
});
test('local file insertion restores visual editor bookmark and uses the active editor API',async()=>{
 const {context,document,requests}=setup();let inserted,restored;
 context.Joomla={Text:{_:k=>k},getOptions:()=>({url:'/index.php'})};
 context.JoomlaEditorButton={registerAction(){}};context.JoomlaEditor={};context.parseInput=()=>({shortcode:'{youtube id="abcdefghijk"}'});
 const source=readFileSync(new URL('../plg_editors-xtd_bslmedia/media/button.js',import.meta.url),'utf8');
 vm.runInContext(source.slice(source.indexOf('const text =')).replace('export function','function'),context);
 const raw={focus(){},selection:{getBookmark:()=>({position:17}),moveToBookmark:b=>restored=b.position}};
 context.openMediaDialog({getRawInstance:()=>raw,replaceSelection:s=>inserted=s});
 requests[0].resolve({ok:true,json:async()=>page});await tick();
 const dialog=document.body.children[0], local=dialog.children[3];
 local.children[3].children[0].fire('click');
 const insert=dialog.children.find(n=>n.className==='bsl-media-insert');assert.equal(insert.disabled,false);insert.fire('click');
 assert.equal(restored,17);assert.equal(inserted,item.shortcode);assert.equal(document.body.children.length,0);
});
test('provider section cancels the pending list and inserts at the saved source selection',async()=>{
 const {context,document,requests}=setup();let inserted,range;
 context.Joomla={Text:{_:k=>k},getOptions:()=>({url:'/index.php'})};
 context.JoomlaEditorButton={registerAction(){}};context.JoomlaEditor={};
 context.parseInput=()=>({shortcode:'{bslyoutube video="abcdefghijk"}',provider:'YouTube'});
 const source=readFileSync(new URL('../plg_editors-xtd_bslmedia/media/button.js',import.meta.url),'utf8');
 vm.runInContext(source.slice(source.indexOf('const text =')).replace('export function','function'),context);
 const textarea={selectionStart:4,selectionEnd:9,focus(){},setSelectionRange:(...r)=>range=r};
 context.openMediaDialog({getRawInstance:()=>({isHidden:()=>true,getElement:()=>textarea}),replaceSelection:s=>inserted=s});
 const dialog=document.body.children[0];dialog.children[1].children[1].fire('click');
 assert.equal(requests[0].options.signal.aborted,true);
 const input=dialog.children[2].children[0];input.value='https://youtu.be/abcdefghijk';input.fire('input');
 requests[0].resolve({ok:true,json:async()=>page});await tick();
 dialog.children.find(n=>n.className==='bsl-media-insert').fire('click');
 assert.equal(inserted,'{bslyoutube video="abcdefghijk"}');assert.deepEqual(range,[4,9]);
});
test('formatting updates preview, blocks invalid sizes, and inserts the chosen options at the saved cursor',async()=>{
 const {context,document,requests}=setup();let inserted;
 context.Joomla={Text:{_:k=>k},getOptions:()=>({url:'/index.php'})};
 context.JoomlaEditorButton={registerAction(){}};context.JoomlaEditor={};context.parseInput=()=>({shortcode:'{bslyoutube video="abcdefghijk"}',provider:'YouTube'});
 const source=readFileSync(new URL('../plg_editors-xtd_bslmedia/media/button.js',import.meta.url),'utf8');
 vm.runInContext(source.slice(source.indexOf('const text =')).replace('export function','function'),context);
 context.openMediaDialog({replaceSelection:s=>inserted=s});
 requests[0].resolve({ok:true,json:async()=>page});await tick();
 const dialog=document.body.children[0];dialog.children[3].children[3].children[0].fire('click');
 const controls=dialog.querySelectorAll('input').concat(dialog.querySelectorAll('select'));
 const control=className=>controls.find(el=>el.className===className);
 const align=control('bsl-format-align'),width=control('bsl-format-width'),gap=control('bsl-format-gap');
 const insert=dialog.children.find(el=>el.className==='bsl-media-insert');
 assert.equal(insert.disabled,false);
 align.value='right';align.fire('change');width.value='320';width.fire('input');gap.value='24';gap.fire('input');
 assert.equal(dialog.querySelectorAll('pre')[0].textContent,'{bslaudio file="media-abc" align="right" width="320" gap="24"}');
 width.value='4097';width.fire('input');assert.equal(insert.disabled,true);
 width.value='320';width.fire('input');assert.equal(insert.disabled,false);
 dialog.children[1].children[1].fire('click');
 const input=dialog.children[2].children[0];input.value='https://youtu.be/abcdefghijk';input.fire('input');
 insert.fire('click');assert.equal(inserted,'{bslyoutube video="abcdefghijk" align="right" width="320" gap="24"}');
});
