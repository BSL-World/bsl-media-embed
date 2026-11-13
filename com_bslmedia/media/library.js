(() => {
  'use strict';
  const root = document.getElementById('bsl-library');
  if (!root) return;
  const files = document.getElementById('bsl-files');
  const trigger = document.getElementById('bsl-upload-trigger');
  const upload = document.getElementById('bsl-upload-form');
  let uploading = false;
  if (files && trigger && upload) {
    let policy,messages,ready=false;
    const status=document.getElementById('bsl-upload-status');
    try{
      policy=JSON.parse(root.dataset.uploadPolicy);messages=JSON.parse(root.dataset.uploadMessages);
      ready=typeof globalThis.BslMediaUploadLimits?.check==='function';
    }catch{ready=false;}
    const format=bytes=>new Intl.NumberFormat(document.documentElement?.lang||undefined,{maximumFractionDigits:2}).format(bytes/(bytes>=1e9?1e9:1e6))+(bytes>=1e9?' GB':' MB');
    const exact=bytes=>format(bytes)+' ('+new Intl.NumberFormat(document.documentElement?.lang||undefined).format(bytes)+' B)';
    const fill=(template,args)=>String(template).replace(/%s/g,()=>String(args.shift()??''));
    function preflight(){
      if(!ready){status.textContent=root.dataset.uploadUnavailable;return false;}
      document.getElementById('bsl-selected-count').value=files.files.length;
      const fields=[...new FormData(upload)].filter(([,value])=>typeof value==='string');
      const result=globalThis.BslMediaUploadLimits.check(Array.from(files.files),policy,fields);
      if(result.ok){status.textContent='';return true;}
      if(['component','upload'].includes(result.reason))status.textContent=fill(messages.file,[result.name,exact(result.actual),exact(result.limit),messages[result.reason]]);
      else if(result.reason==='post')status.textContent=fill(messages.post,[exact(result.actual),exact(result.limit)]);
      else if(result.reason==='count')status.textContent=fill(messages.count,[result.actual,result.limit]);
      else if(result.reason==='invalid')status.textContent=fill(messages.invalid,[result.name]);
      else status.textContent=messages[result.reason]||root.dataset.uploadUnavailable;
      return false;
    }
    trigger.disabled=!ready||!policy?.enabled||!policy?.valid||policy?.maxFiles===0;
    if(trigger.disabled)status.textContent=ready?(messages[policy?.valid?'disabled':'config']||root.dataset.uploadUnavailable):root.dataset.uploadUnavailable;
    trigger.addEventListener('click', () => { if (!uploading&&!trigger.disabled) files.click(); });
    files.addEventListener('change', () => {
      if (uploading || !files.files.length) return;
      if(!preflight())return;
      upload.requestSubmit();
    });
    upload.addEventListener('submit', event => {
      if (uploading || !files.files.length) { event.preventDefault(); return; }
      if(!preflight()){event.preventDefault();return;}
      uploading = true; trigger.disabled = true;
      document.getElementById('bsl-upload-status').textContent = root.dataset.uploading;
    });
  }
  root.querySelectorAll('.bsl-copy').forEach(button => button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.copy);
    let copied = false;
    try { await navigator.clipboard.writeText(input.value); copied = true; }
    catch { try { input.focus(); input.select(); copied = document.execCommand('copy'); } catch { copied = false; } }
    document.getElementById('bsl-copy-status').textContent = copied ? root.dataset.copied : root.dataset.copyFailed;
  }));
  const table = document.getElementById('bsl-media-table');
  const search = document.getElementById('bsl-file-search');
  if (table) {
    const body = table.querySelector('tbody'), rows = Array.from(body.rows);
    const compare = new Intl.Collator(document.documentElement?.lang || undefined, { numeric: true, sensitivity: 'base' }).compare;
    const buttons = table.querySelectorAll('[data-sort]');
    let sort = 'name', direction = 1;
    function draw() {
      const query = (search?.value || '').trim().toLocaleLowerCase();
      rows.sort((a,b) => direction * (sort === 'name' ? compare(a.dataset.name,b.dataset.name) : Number(a.dataset[sort])-Number(b.dataset[sort])) || compare(a.dataset.name,b.dataset.name));
      rows.forEach(row => { row.hidden = !row.dataset.name.toLocaleLowerCase().includes(query); body.append(row); });
      buttons.forEach(button => button.closest('th').setAttribute('aria-sort',button.dataset.sort === sort ? (direction === 1 ? 'ascending' : 'descending') : 'none'));
      const empty = document.getElementById('bsl-empty');
      if (empty) empty.hidden = rows.some(row => !row.hidden);
    }
    search?.addEventListener('input',draw);
    buttons.forEach(button => button.addEventListener('click',() => { direction = sort === button.dataset.sort ? -direction : 1; sort = button.dataset.sort; draw(); }));
    draw();
  }
  // The only folder dialog selects the library root, inside administrator settings.
  const dialog = document.getElementById('bsl-folder-picker');
  if (!dialog) return;
  const choose = document.getElementById('bsl-select-folder'), up = document.getElementById('bsl-up');
  let relative = '', current = '', selected = null, request = 0;
  function locationLabel(location) {
    const labels={site:root.dataset.siteLabel,neighbour:root.dataset.neighbourLabel,container:root.dataset.containerLabel};
    return [labels[location.area],...location.parts].join(' → ');
  }
  async function load(next,initial='') {
    const seq = ++request;
    choose.disabled = true; up.disabled = true;
    document.getElementById('bsl-picker-error').textContent = '';
    try {
      const url = new URL('index.php',location.href);
      url.search = new URLSearchParams({option:'com_bslmedia',task:'manage.browse',mode:'root',relative:next,initial});
      const response = await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || data?.error) throw new Error(typeof data?.error==='string'?data.error:root.dataset.browseError);
      if (!data||!data.location||!['site','neighbour','container'].includes(data.location.area)||!['site','container'].includes(data.boundaryArea)||!Array.isArray(data.location.parts)||!data.location.parts.every(part=>typeof part==='string')||typeof data.relative!=='string'
          || !Array.isArray(data.folders)||!data.folders.every(folder=>folder&&typeof folder.name==='string'&&typeof folder.relative==='string')) throw new Error(root.dataset.browseError);
      if (seq !== request) return;
      current = locationLabel(data.location); relative = data.relative; selected = data.relative;
      document.getElementById('bsl-picker-path').textContent = current;
      document.getElementById('bsl-picker-boundary').textContent = data.boundaryArea==='site'?root.dataset.siteLabel:root.dataset.containerLabel;
      up.disabled = !relative;
      document.getElementById('bsl-picker-folders').replaceChildren();
      data.folders.forEach(folder => {
        const li = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.className = 'btn btn-link'; button.textContent = folder.name;
        button.addEventListener('click',() => load(folder.relative));
        li.append(button); document.getElementById('bsl-picker-folders').append(li);
      });
      choose.disabled = false;
    } catch (error) {
      if (seq === request) {
        document.getElementById('bsl-picker-error').textContent = error instanceof SyntaxError ? root.dataset.browseError : (error.message || root.dataset.browseError);
        // Keep the last valid path/list after a permission or network failure.
        choose.disabled = !current; up.disabled = !relative;
      }
    }
  }
  document.getElementById('bsl-browse').addEventListener('click',() => {
    relative='';current='';selected=null;document.getElementById('bsl-picker-path').textContent='';
    document.getElementById('bsl-picker-boundary').textContent='';document.getElementById('bsl-picker-folders').replaceChildren();
    dialog.showModal();load('','library');
  });
  document.getElementById('bsl-site-home').addEventListener('click',() => load('','site'));
  up.addEventListener('click',() => load(relative.split('/').slice(0,-1).join('/')));
  choose.addEventListener('click',() => {
    if(current&&selected!==null){
      document.getElementById('bsl-root-choice').value=selected;document.getElementById('bsl-root-selected').value='1';
      document.getElementById('bsl-root-location').textContent=current;++request;dialog.close();
    }
  });
  document.getElementById('bsl-close-picker').addEventListener('click',() => {++request;dialog.close();});
  dialog.addEventListener('cancel',() => {++request;});
})();
