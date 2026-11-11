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
  const filter = document.getElementById('bsl-folder-filter');
  if (table && filter) {
    const body = table.querySelector('tbody');
    const rows = Array.from(body.rows);
    const compare = new Intl.Collator(document.documentElement?.lang || undefined, { numeric: true, sensitivity: 'base' }).compare;
    const folders = [...new Set(rows.map(row => row.dataset.folder).filter(Boolean))].sort(compare);
    folders.forEach(folder => { const option = document.createElement('option'); option.value = `folder:${folder}`; option.textContent = folder; filter.append(option); });
    let sort = 'name', direction = 1;
    const buttons = table.querySelectorAll('[data-sort]');
    function draw() {
      rows.sort((a, b) => {
        const order = sort === 'name' ? compare(a.dataset.name, b.dataset.name) : Number(a.dataset[sort]) - Number(b.dataset[sort]);
        return direction * order || compare(a.dataset.name, b.dataset.name) || compare(a.dataset.folder, b.dataset.folder);
      });
      rows.forEach(row => { row.hidden = filter.value !== '*' && `folder:${row.dataset.folder}` !== filter.value; body.append(row); });
      buttons.forEach(button => button.closest('th').setAttribute('aria-sort', button.dataset.sort === sort ? (direction === 1 ? 'ascending' : 'descending') : 'none'));
    }
    filter.addEventListener('change', draw);
    buttons.forEach(button => button.addEventListener('click', () => { direction = sort === button.dataset.sort ? -direction : 1; sort = button.dataset.sort; draw(); }));
    draw(); document.getElementById('bsl-table-controls').hidden = false;
  }
  const dialog = document.getElementById('bsl-folder-picker');
  if (!dialog) return;
  const scope = document.getElementById('bsl-scope');
  const choose = document.getElementById('bsl-select-folder');
  const create = document.getElementById('bsl-create-form');
  let mode = 'root', relative = '', current = '', request = 0;
  async function readJSON(response) {
    let data;
    try { data = await response.json(); } catch { throw new Error(root.dataset.browseError); }
    if (!response.ok || data.error) throw new Error(data.error || root.dataset.browseError);
    return data;
  }
  async function load(next) {
    const seq = ++request;
    relative = next; current = ''; choose.disabled = true;
    if (create) create.hidden = true;
    document.getElementById('bsl-picker-error').textContent = '';
    document.getElementById('bsl-picker-path').textContent = '';
    document.getElementById('bsl-picker-folders').replaceChildren();
    try {
      const url = new URL('index.php', location.href);
      url.search = new URLSearchParams({ option: 'com_bslmedia', task: 'manage.browse', mode, scope: scope.value, relative: next });
      const data = await readJSON(await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
      if (seq !== request) return;
      current = data.path; relative = data.relative;
      document.getElementById('bsl-picker-path').textContent = current;
      document.getElementById('bsl-up').disabled = !relative;
      data.folders.forEach(folder => {
        const li = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.className = 'btn btn-link'; button.textContent = folder.name;
        button.addEventListener('click', () => load(folder.relative));
        li.append(button); document.getElementById('bsl-picker-folders').append(li);
      });
      if (create && mode === 'destination' && data.canCreate) {
        create.hidden = false; document.getElementById('bsl-create-parent').value = relative;
      }
      choose.disabled = false;
    } catch (error) {
      if (seq === request) document.getElementById('bsl-picker-error').textContent = error.message || root.dataset.browseError;
    }
  }
  function openPicker(nextMode) {
    mode = nextMode;
    document.getElementById('bsl-picker-title').textContent = mode === 'root' ? root.dataset.rootTitle : root.dataset.destinationTitle;
    document.getElementById('bsl-root-scope').hidden = mode !== 'root';
    document.getElementById('bsl-picker-depth').hidden = mode !== 'destination';
    dialog.showModal();
    load(mode === 'destination' ? document.getElementById('bsl-destination').value : '');
  }
  document.getElementById('bsl-browse')?.addEventListener('click', () => openPicker('root'));
  document.getElementById('bsl-destination-browse')?.addEventListener('click', () => openPicker('destination'));
  scope.addEventListener('change', () => load(''));
  document.getElementById('bsl-up').addEventListener('click', () => load(relative.split('/').slice(0, -1).join('/')));
  choose.addEventListener('click', () => {
    if (!current) return;
    if (mode === 'root') document.getElementById('bsl-root').value = current;
    else {
      document.getElementById('bsl-destination').value = relative;
      document.getElementById('bsl-destination-path').textContent = current;
    }
    ++request; dialog.close();
  });
  if (create) create.addEventListener('submit', async event => {
    event.preventDefault();
    if (mode !== 'destination' || create.hidden) return;
    const button = document.getElementById('bsl-create-button');
    if (button.disabled) return;
    button.disabled = true; choose.disabled = true;
    const seq = ++request;
    try {
      const data = await readJSON(await fetch(create.action, { method: 'POST', credentials: 'same-origin', body: new FormData(create), headers: { Accept: 'application/json' } }));
      if (seq !== request) return;
      document.getElementById('bsl-new-folder').value = '';
      await load(data.relative);
    } catch (error) {
      if (seq === request) { document.getElementById('bsl-picker-error').textContent = error.message || root.dataset.browseError; choose.disabled = !current; }
    } finally { button.disabled = false; }
  });
  function close() { ++request; dialog.close(); }
  document.getElementById('bsl-close-picker').addEventListener('click', close);
  dialog.addEventListener('cancel', () => { ++request; });
})();
