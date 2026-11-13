import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
const source=readFileSync(new URL('../com_bslmedia/media/library.js',import.meta.url),'utf8');
function run(clipboard,legacy){
  const handlers={};let focused=false,selected=false;
  const button={dataset:{copy:'code'},addEventListener:(name,fn)=>{handlers[name]=fn;}};
  const input={value:'{bslaudio file="sample.opus"}',focus(){focused=true;},select(){selected=true;}};
  const status={textContent:''};
  const root={dataset:{copied:'copied',copyFailed:'failed'},querySelectorAll:()=>[button]};
  const document={getElementById:id=>({'bsl-library':root,code:input,'bsl-copy-status':status}[id]??null),execCommand:legacy};
  vm.runInNewContext(source,{document,navigator:{clipboard}});
  return {click:handlers.click,status,input,selected:()=>focused&&selected};
}
test('copies complete shortcode without changing its text',async()=>{
  let copied;const ui=run({writeText:async s=>{copied=s;}},()=>false);await ui.click();
  assert.equal(copied,ui.input.value);assert.equal(ui.status.textContent,'copied');
});
test('clipboard denial falls back to selecting and copying the readonly field',async()=>{
  const ui=run({writeText:async()=>{throw Error('denied');}},()=>true);await ui.click();
  assert.equal(ui.selected(),true);assert.equal(ui.status.textContent,'copied');
});
test('reports copy failure instead of an unhandled error',async()=>{
  const ui=run(undefined,()=>{throw Error('unsupported');});await ui.click();assert.equal(ui.status.textContent,'failed');
});

function uploader(overrides={}){
  const handlers=new Map();let submitted=0,opened=0;
  const node=id=>({addEventListener:(event,fn)=>handlers.set(id+':'+event,fn)});
  const files={...node('files'),files:[],click(){opened++;}};
  const trigger={...node('trigger'),disabled:true};
  const form={...node('form'),requestSubmit(){const event={defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};handlers.get('form:submit')(event);if(!event.defaultPrevented)submitted++;}};
  const count={value:0},status={textContent:''};
  const policy={valid:true,enabled:true,component:1024000000,upload:134217728,post:167772160,maxFiles:20,reserveBase:65536,reserveFile:4096,...overrides};
  const messages={file:'%s: %s exceeds %s (%s)',post:'request %s exceeds %s',count:'count %s exceeds %s',component:'component',upload:'upload_max_filesize',config:'config',disabled:'disabled',invalid:'invalid %s'};
  const root={dataset:{uploading:'uploading',uploadPolicy:JSON.stringify(policy),uploadMessages:JSON.stringify(messages),uploadUnavailable:'unavailable'},querySelectorAll:()=>[]};
  const document={getElementById:id=>({'bsl-library':root,'bsl-files':files,'bsl-upload-trigger':trigger,'bsl-upload-form':form,'bsl-selected-count':count,'bsl-upload-status':status}[id]??null)};
  const context=vm.createContext({document,TextEncoder,FormData:class{*[Symbol.iterator](){yield ['task','manage.upload'];yield ['selected_count',String(count.value)];}}});
  vm.runInContext(readFileSync(new URL('../com_bslmedia/media/upload-limits.js',import.meta.url),'utf8'),context);
  vm.runInContext(source,context);
  return {files,trigger,status,count,handlers,form,submitted:()=>submitted,opened:()=>opened};
}
test('selecting files submits immediately once, with no second confirmation',()=>{
  const ui=uploader();assert.equal(ui.trigger.disabled,false);
  ui.handlers.get('trigger:click')();assert.equal(ui.opened(),1);
  ui.files.files=[{name:'one.mp3',size:1000},{name:'two.mp4',size:2000}];ui.handlers.get('files:change')();
  assert.equal(ui.submitted(),1);assert.equal(ui.count.value,2);assert.equal(ui.trigger.disabled,true);assert.equal(ui.status.textContent,'uploading');
  ui.handlers.get('files:change')();ui.handlers.get('trigger:click')();assert.equal(ui.submitted(),1);assert.equal(ui.opened(),1);
});
test('cancelling file selection does not send an empty upload',()=>{
  const ui=uploader();ui.handlers.get('files:change')();assert.equal(ui.submitted(),0);assert.equal(ui.trigger.disabled,false);
});
test('oversized file selection does not start submission or lock the upload button',()=>{
 const ui=uploader();ui.files.files=[{name:'large.mp4',size:213578537}];ui.handlers.get('files:change')();
 assert.equal(ui.submitted(),0);assert.equal(ui.trigger.disabled,false);assert.match(ui.status.textContent,/upload_max_filesize/);
});
test('submit handler rechecks a manually submitted selection and its total POST budget',()=>{
 const ui=uploader({upload:268435456});ui.files.files=[{name:'large.mp4',size:213578537}];
 ui.form.requestSubmit();
 assert.equal(ui.submitted(),0);assert.equal(ui.trigger.disabled,false);assert.match(ui.status.textContent,/request/);
 ui.files.files=[{name:'small.mp4',size:1000}];ui.handlers.get('files:change')();assert.equal(ui.submitted(),1);
});
