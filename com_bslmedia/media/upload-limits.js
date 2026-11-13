/* Byte-only arithmetic. No file contents are read or serialized for preflight. */
(() => {
  'use strict';
  const utf8 = value => new TextEncoder().encode(String(value)).length;
  function check(files, policy, fields=[]) {
    const fail=(reason,extra={})=>({ok:false,reason,...extra});
    if(!policy?.valid)return fail('config');
    if(!policy.enabled || policy.maxFiles===0)return fail('disabled');
    if(!files.length)return fail('empty');
    if(files.length>policy.maxFiles)return fail('count',{actual:files.length,limit:policy.maxFiles});
    let total=0, text=0;
    for(const [name,value] of fields)text+=4*(utf8(name)+utf8(value))+1024;
    let overhead=Math.max(policy.reserveBase,text+1024);
    for(const file of files){
      if(!Number.isSafeInteger(file.size)||file.size<=0)return fail('invalid',{name:file.name||''});
      for(const kind of ['component','upload'])if(policy[kind]>0&&file.size>policy[kind])return fail(kind,{name:file.name,actual:file.size,limit:policy[kind]});
      total+=file.size;
      overhead+=Math.max(policy.reserveFile,1024+4*(utf8(file.name||'')+utf8(file.type||'')+utf8('media[]')));
      if(!Number.isSafeInteger(total+overhead))return fail('invalid',{name:file.name||''});
    }
    const estimated=total+overhead;
    if(policy.post>0&&estimated>policy.post)return fail('post',{actual:estimated,limit:policy.post,total,overhead});
    return {ok:true,total,overhead,estimated};
  }
  globalThis.BslMediaUploadLimits={check};
})();
