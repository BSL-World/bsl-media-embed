(() => {
  'use strict';
  const root = document.getElementById('bsl-library');
  if (!root) return;
  const files = document.getElementById('bsl-files');
  const trigger = document.getElementById('bsl-upload-trigger');
  const upload = document.getElementById('bsl-upload-form');
  let uploading = false;
  if (files && trigger && upload) {
    trigger.disabled = false;
    trigger.addEventListener('click', () => { if (!uploading) files.click(); });
    files.addEventListener('change', () => {
      if (uploading || !files.files.length) return;
      document.getElementById('bsl-selected-count').value = files.files.length;
      upload.requestSubmit();
    });
    upload.addEventListener('submit', event => {
      if (uploading || !files.files.length) { event.preventDefault(); return; }
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
  const scope = document.getElementById('bsl-scope'), choose = document.getElementById('bsl-select-folder');
  let relative = '', current = '', request = 0;
  async function load(next) {
    const seq = ++request;
    relative = next; current = ''; choose.disabled = true;
    document.getElementById('bsl-picker-error').textContent = '';
    document.getElementById('bsl-picker-path').textContent = '';
    document.getElementById('bsl-picker-folders').replaceChildren();
    try {
      const url = new URL('index.php',location.href);
      url.search = new URLSearchParams({option:'com_bslmedia',task:'manage.browse',mode:'root',scope:scope.value,relative:next});
      const response = await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || data.error) throw new Error(data.error || root.dataset.browseError);
      if (seq !== request) return;
      current = data.path; relative = data.relative;
      document.getElementById('bsl-picker-path').textContent = current;
      document.getElementById('bsl-up').disabled = !relative;
      data.folders.forEach(folder => {
        const li = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.className = 'btn btn-link'; button.textContent = folder.name;
        button.addEventListener('click',() => load(folder.relative));
        li.append(button); document.getElementById('bsl-picker-folders').append(li);
      });
      choose.disabled = false;
    } catch (error) {
      if (seq === request) document.getElementById('bsl-picker-error').textContent = error instanceof SyntaxError ? root.dataset.browseError : (error.message || root.dataset.browseError);
    }
  }
  document.getElementById('bsl-browse').addEventListener('click',() => { dialog.showModal(); load(''); });
  scope.addEventListener('change',() => load(''));
  document.getElementById('bsl-up').addEventListener('click',() => load(relative.split('/').slice(0,-1).join('/')));
  choose.addEventListener('click',() => { if(current){document.getElementById('bsl-root').value=current;++request;dialog.close();} });
  document.getElementById('bsl-close-picker').addEventListener('click',() => {++request;dialog.close();});
  dialog.addEventListener('cancel',() => {++request;});
})();
