import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../com_bslmedia/media/library.js',import.meta.url),'utf8');
function run(){
 const rows=[
  {dataset:{name:'File10.mp3',folder:'',size:'100',modified:'100'},hidden:false},
  {dataset:{name:'File2.mp3',folder:'Section',size:'9',modified:'20'},hidden:false},
  {dataset:{name:'Clip.mp4',folder:'Section/Subsection',size:'20',modified:'3'},hidden:false}
 ];
 const body={rows,append(row){const i=this.rows.indexOf(row);if(i>=0)this.rows.splice(i,1);this.rows.push(row);}};
 const clicks={},headers={};
 const buttons=['name','size','modified'].map(key=>({dataset:{sort:key},addEventListener:(event,fn)=>{clicks[key]=fn;},closest:()=>({setAttribute:(name,value)=>{headers[key]=value;}})}));
 const table={querySelector:()=>body,querySelectorAll:()=>buttons};
 let change;const search={value:'',addEventListener:(event,fn)=>{change=fn;}};
 const destination={value:'Section'};const empty={hidden:true};
 const root={querySelectorAll:()=>[]};
 const document={documentElement:{lang:'en'},getElementById:id=>({'bsl-library':root,'bsl-media-table':table,'bsl-file-search':search,'bsl-empty':empty,'bsl-destination':destination}[id]??null),createElement:()=>({})};
 vm.runInNewContext(source,{document,Intl});
 return {body,clicks,headers,empty,destination,search(value){search.value=value;change();}};
}
test('sorts names naturally, sizes numerically, and dates by timestamp in both directions',()=>{
 const ui=run();assert.deepEqual(ui.body.rows.map(r=>r.dataset.name),['Clip.mp4','File2.mp3','File10.mp3']);
 ui.clicks.size();assert.deepEqual(ui.body.rows.map(r=>r.dataset.size),['9','20','100']);assert.equal(ui.headers.size,'ascending');
 ui.clicks.size();assert.deepEqual(ui.body.rows.map(r=>r.dataset.size),['100','20','9']);assert.equal(ui.headers.size,'descending');
 ui.clicks.modified();assert.deepEqual(ui.body.rows.map(r=>r.dataset.modified),['3','20','100']);
 ui.clicks.modified();assert.deepEqual(ui.body.rows.map(r=>r.dataset.modified),['100','20','3']);
});
test('search filters the open folder without changing upload target or losing sort/row identity',()=>{
 const ui=run();
 ui.search('file2');assert.deepEqual(ui.body.rows.filter(r=>!r.hidden).map(r=>r.dataset.name),['File2.mp3']);
 ui.clicks.size();assert.equal(ui.body.rows.filter(r=>!r.hidden)[0].dataset.name,'File2.mp3');
 ui.search('missing');assert.equal(ui.empty.hidden,false);
 ui.search('');assert.equal(ui.body.rows.filter(r=>!r.hidden).length,3);assert.equal(ui.empty.hidden,true);
 assert.equal(ui.destination.value,'Section');
});
