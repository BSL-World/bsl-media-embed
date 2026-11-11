import { JoomlaEditor, JoomlaEditorButton } from 'editor-api';
// Carry Joomla's asset version to the dependency so a package upgrade cannot keep an old parser cached.
const parserUrl = new URL('./parser.js', import.meta.url);
parserUrl.search = new URL(import.meta.url).search;
const { parseInput } = await import(parserUrl.href);
const pickerUrl = new URL('./library-picker.js', import.meta.url);
pickerUrl.search = new URL(import.meta.url).search;
const { createLibraryPicker } = await import(pickerUrl.href);
const text = key => Joomla.Text._(`PLG_EDITORSXTD_BSLMEDIA_${key}`);
export function openMediaDialog(editor) {
  if (document.querySelector('dialog.bsl-media-dialog')) return;
  const raw = editor.getRawInstance?.();
  let bookmark, range, textarea;
  try {
    const sourceMode = raw?.isHidden?.() === true;
    textarea = sourceMode ? raw.getElement?.() : raw;
    if (typeof textarea?.selectionStart === 'number') range = [textarea.selectionStart, textarea.selectionEnd];
    if (!sourceMode) bookmark = raw?.selection?.getBookmark?.(2, true);
  } catch {
    // An unavailable visual selection must not prevent source-mode insertion through the editor API.
    bookmark = undefined;
  }
  const dialog = document.createElement('dialog');
  dialog.className = 'bsl-media-dialog';
  dialog.style.cssText = 'max-width:36rem;width:90%;border:1px solid #777;padding:1.5rem;';
  const heading = document.createElement('h2'); heading.textContent = 'BSL Media';
  const label = document.createElement('label'); label.textContent = text('INPUT');
  const input = document.createElement('textarea');
  input.rows = 6; input.maxLength = 32768; input.style.cssText = 'display:block;width:100%;box-sizing:border-box;'; label.append(input);
  const status = document.createElement('p'); status.setAttribute('aria-live', 'polite');
  const preview = document.createElement('pre'); preview.style.whiteSpace = 'pre-wrap';
  const cancel = document.createElement('button'); cancel.className='bsl-media-cancel'; cancel.type = 'button'; cancel.textContent = text('CANCEL');
  const insert = document.createElement('button'); insert.className='bsl-media-insert'; insert.type = 'button'; insert.textContent = text('INSERT'); insert.disabled = true;
  let result;
  input.addEventListener('input', () => {
    try {
      result = parseInput(input.value); preview.textContent = result.shortcode;
      status.textContent = result.provider + (result.parametersRemoved ? ': ' + text('REMOVED') : ''); insert.disabled = false;
    } catch {
      result = null; preview.textContent = ''; status.textContent = input.value ? text('INVALID') : ''; insert.disabled = true;
    }
  });
  const endpoint=Joomla.getOptions?.('bslmedia.library')?.url;
  let picker;
  const tabs=document.createElement('div');
  if(endpoint) {
    picker=createLibraryPicker({url:endpoint,text,
      select(item){result=item;preview.textContent=item?.shortcode||'';insert.disabled=!item;},
      status(message){status.textContent=message;}});
    const files=document.createElement('button'), links=document.createElement('button');
    files.type=links.type='button';files.textContent=text('FILES');links.textContent=text('LINKS');
    const switchPane=local=>{
      label.hidden=local;picker.pane.hidden=!local;result=null;preview.textContent='';status.textContent='';insert.disabled=true;
      files.setAttribute('aria-pressed',String(local));links.setAttribute('aria-pressed',String(!local));
      if(local)picker.load();else {picker.pause();input.value='';input.focus();}
    };
    files.addEventListener('click',()=>switchPane(true));links.addEventListener('click',()=>switchPane(false));
    tabs.append(files,links);label.hidden=true;files.setAttribute('aria-pressed','true');links.setAttribute('aria-pressed','false');
  }
  const close = () => { picker?.stop(); dialog.close(); dialog.remove(); };
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  cancel.addEventListener('click', close);
  insert.addEventListener('click', () => {
    if (!result) return;
    try {
      if (bookmark) { raw.focus(); raw.selection.moveToBookmark(bookmark); }
      if (range) { textarea.focus(); textarea.setSelectionRange(...range); }
      editor.replaceSelection(result.shortcode); insert.disabled = true; result = null; close();
    }
    catch { status.textContent = text('INSERT_ERROR'); }
  });
  dialog.addEventListener('close', () => {picker?.stop();dialog.remove();}, { once: true });
  dialog.append(heading,tabs,label);if(picker)dialog.append(picker.pane);
  dialog.append(status,preview,cancel,insert); dialog.setAttribute('aria-label', 'BSL Media');
  document.body.append(dialog); dialog.showModal(); if(picker)picker.load();else input.focus();
}
JoomlaEditorButton.registerAction('bsl-media', (editor, options = {}) => {
  const target = options.editor ? JoomlaEditor.get(options.editor) : editor;
  if (!target) throw new Error('Editor unavailable');
  openMediaDialog(target);
});
