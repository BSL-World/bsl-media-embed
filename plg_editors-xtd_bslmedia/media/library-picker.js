/** A bounded, read-only picker. File names are always text, never HTML. */
export function createLibraryPicker({url, text, select, status}) {
  const pane=document.createElement('section');
  const make=(tag,label)=>{const el=document.createElement(tag); if(label)el.textContent=label; return el;};
  const search=make('input'); search.type='search'; search.placeholder=text('SEARCH'); search.setAttribute('aria-label',text('SEARCH'));
  search.maxLength=200;
  const folder=make('select'); folder.setAttribute('aria-label',text('FOLDER'));
  const type=make('select'); type.setAttribute('aria-label',text('TYPE'));
  const option=(parent,value,label)=>{const el=make('option',label);el.value=value;parent.append(el);};
  option(folder,'*',text('FOLDER')+': '+text('ALL'));
  option(type,'',text('TYPE')+': '+text('ALL')); for(const ext of ['mp3','mp4','opus'])option(type,ext,ext.toUpperCase());
  const list=make('div'); list.style.cssText='max-height:18rem;overflow:auto;margin:1rem 0;';
  const previous=make('button',text('PREVIOUS')), next=make('button',text('NEXT')), count=make('span');
  previous.type=next.type='button';
  const navigation=make('div'); navigation.append(previous,count,next);
  pane.append(search,folder,type,list,navigation);
  let page=1, pages=1, serial=0, stopped=false, timer, abort;
  async function load() {
    clearTimeout(timer);
    const request=++serial; abort?.abort(); abort=new AbortController();
    select(null); status(text('LOADING')); list.replaceChildren(); previous.disabled=next.disabled=true;
    try {
      const endpoint=new URL(url,window.location.href);
      if(endpoint.origin!==window.location.origin)throw new Error('Origin');
      for(const [key,value] of Object.entries({q:search.value,folder:folder.value,type:type.value,page}))endpoint.searchParams.set(key,value);
      const response=await fetch(endpoint,{credentials:'same-origin',cache:'no-store',signal:abort.signal});
      if(!response.ok)throw new Error('HTTP');
      const data=await response.json();
      if(stopped||request!==serial)return;
      if(!Array.isArray(data.items)||data.items.length>30||!Array.isArray(data.folders))throw new Error('Data');
      const selectedFolder=folder.value;folder.replaceChildren();option(folder,'*',text('FOLDER')+': '+text('ALL'));
      for(const name of data.folders)option(folder,name,name||text('ROOT'));
      folder.value=selectedFolder;
      page=data.page; pages=data.pages;
      count.textContent=` ${page} / ${pages} (${data.total}) `;
      previous.disabled=page<=1;next.disabled=page>=pages;
      for(const item of data.items) {
        if(!/^\{bsl(?:audio|video) file="[A-Za-z0-9][A-Za-z0-9._-]{0,127}"\}$/.test(item.shortcode))throw new Error('Shortcode');
        const row=make('button',`${item.folder?item.folder+'/':''}${item.name} · ${item.type.toUpperCase()}`);
        row.type='button';row.style.cssText='display:block;width:100%;text-align:left;margin:.25rem 0;';
        row.setAttribute('aria-pressed','false');
        row.addEventListener('click',()=>{
          for(const button of list.querySelectorAll('button')){button.setAttribute('aria-pressed','false');button.style.outline='';}
          row.setAttribute('aria-pressed','true');row.style.outline='2px solid currentColor'; select(item);status(data.truncated?text('LIMIT'):'');
        });list.append(row);
      }
      status(data.truncated?text('LIMIT'):(data.items.length?'':text('EMPTY')));
    } catch(error) {
      if(stopped||request!==serial||error.name==='AbortError')return;
      list.replaceChildren();select(null);status(text('LOAD_ERROR'));
    }
  }
  search.addEventListener('input',()=>{clearTimeout(timer);++serial;abort?.abort();select(null);page=1;timer=setTimeout(load,250);});
  for(const field of [folder,type])field.addEventListener('change',()=>{page=1;load();});
  previous.addEventListener('click',()=>{if(page>1){page--;load();}});
  next.addEventListener('click',()=>{if(page<pages){page++;load();}});
  return {pane,load,pause(){++serial;abort?.abort();clearTimeout(timer);},stop(){stopped=true;++serial;abort?.abort();clearTimeout(timer);}};
}
