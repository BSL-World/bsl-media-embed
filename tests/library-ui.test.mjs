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

function uploader(){
  const handlers=new Map();let submitted=0,opened=0;
  const node=id=>({addEventListener:(event,fn)=>handlers.set(id+':'+event,fn)});
  const files={...node('files'),files:[],click(){opened++;}};
  const trigger={...node('trigger'),disabled:true};
  const form={...node('form'),requestSubmit(){const event={defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};handlers.get('form:submit')(event);if(!event.defaultPrevented)submitted++;}};
  const count={value:0},status={textContent:''};
  const root={dataset:{uploading:'uploading'},querySelectorAll:()=>[]};
  const document={getElementById:id=>({'bsl-library':root,'bsl-files':files,'bsl-upload-trigger':trigger,'bsl-upload-form':form,'bsl-selected-count':count,'bsl-upload-status':status}[id]??null)};
  vm.runInNewContext(source,{document});
  return {files,trigger,status,count,handlers,submitted:()=>submitted,opened:()=>opened};
}
test('selecting files submits immediately once, with no second confirmation',()=>{
  const ui=uploader();assert.equal(ui.trigger.disabled,false);
  ui.handlers.get('trigger:click')();assert.equal(ui.opened(),1);
  ui.files.files=[{},{}];ui.handlers.get('files:change')();
  assert.equal(ui.submitted(),1);assert.equal(ui.count.value,2);assert.equal(ui.trigger.disabled,true);assert.equal(ui.status.textContent,'uploading');
  ui.handlers.get('files:change')();ui.handlers.get('trigger:click')();assert.equal(ui.submitted(),1);assert.equal(ui.opened(),1);
});
test('cancelling file selection does not send an empty upload',()=>{
  const ui=uploader();ui.handlers.get('files:change')();assert.equal(ui.submitted(),0);assert.equal(ui.trigger.disabled,false);
});
