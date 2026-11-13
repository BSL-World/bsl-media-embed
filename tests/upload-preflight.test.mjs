import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const context=vm.createContext({TextEncoder});
vm.runInContext(readFileSync(new URL('../com_bslmedia/media/upload-limits.js',import.meta.url),'utf8'),context);
const check=context.BslMediaUploadLimits.check;
const policy={valid:true,enabled:true,component:1024000000,upload:134217728,post:167772160,maxFiles:20,reserveBase:65536,reserveFile:4096};
const file=(size,name='test.mp4')=>({size,name,type:'video/mp4'});
test('smaller file and multiple smaller files fit without reading their contents',()=>{
 assert.equal(check([file(1000000)],policy).ok,true);
 const result=check([file(1000000),file(2000000)],policy,[['folder','Technical'],['csrf','1']]);
 assert.equal(result.ok,true);assert.equal(result.total,3000000);assert.ok(result.estimated>3000000);
});
test('per-file PHP limit wins over the larger component allowance',()=>{
 const result=check([file(134217729)],policy);assert.equal(result.ok,false);assert.equal(result.reason,'upload');assert.equal(result.limit,134217728);
 assert.equal(check([file(200)],{...policy,component:100}).reason,'component');
});
test('a combined request over post_max_size is rejected even when each file fits',()=>{
 const result=check([file(90000000),file(90000000)],policy);assert.equal(result.reason,'post');assert.equal(result.limit,167772160);
 assert.equal(check([file(167772160)],{...policy,upload:268435456}).reason,'post');
});
test('reported 213578537-byte selection cannot pass a 160M POST cap',()=>{
 const result=check([file(213578537)],{...policy,upload:268435456});assert.equal(result.reason,'post');assert.ok(result.actual>213578537);
 assert.equal(check([file(213578537)],policy).reason,'upload');
 assert.equal(check([file(213578537)],{...policy,upload:268435456,post:285212672}).ok,true);
});
test('multipart reserves account for Unicode names and actual text fields near the cap',()=>{
 const text='д'.repeat(40000),name='файл'.repeat(500)+'.mp3';
 const result=check([file(1000,name)],{...policy,post:100000},[['folder',text]]);assert.equal(result.reason,'post');
 assert.equal(check([file(1000)],{...policy,post:0,upload:0}).ok,true);
});
test('disabled uploads, file-count cap and invalid sizes fail before submission',()=>{
 assert.equal(check([file(10)],{...policy,enabled:false}).reason,'disabled');
 assert.equal(check([file(10),file(20)],{...policy,maxFiles:1}).reason,'count');
 assert.equal(check([file(0)],policy).reason,'invalid');assert.equal(check([file(Number.MAX_SAFE_INTEGER+1)],policy).reason,'invalid');
 assert.equal(check([file(1)],{...policy,valid:false}).reason,'config');
});
