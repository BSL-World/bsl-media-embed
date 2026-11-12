import { JoomlaEditor, JoomlaEditorButton } from 'editor-api';
// Carry Joomla's asset version to the dependency so a package upgrade cannot keep an old parser cached.
const parserUrl = new URL('./parser.js', import.meta.url);
parserUrl.search = new URL(import.meta.url).search;
const { parseInput } = await import(parserUrl.href);
const pickerUrl = new URL('./library-picker.js', import.meta.url);
pickerUrl.search = new URL(import.meta.url).search;
const { createLibraryPicker } = await import(pickerUrl.href);
const formattingUrl = new URL('./formatting.js', import.meta.url);
formattingUrl.search = new URL(import.meta.url).search;
const { formatShortcode } = await import(formattingUrl.href);
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
  dialog.style.cssText = 'max-width:36rem;width:90%;max-height:90vh;overflow:auto;box-sizing:border-box;border:1px solid #777;padding:1.5rem;';
  const heading = document.createElement('h2'); heading.textContent = 'BSL Media';
  const label = document.createElement('label'); label.textContent = text('INPUT');
  const input = document.createElement('textarea');
  input.rows = 6; input.maxLength = 32768; input.style.cssText = 'display:block;width:100%;box-sizing:border-box;'; label.append(input);
  const status = document.createElement('p'); status.setAttribute('aria-live', 'polite');
  const preview = document.createElement('pre'); preview.style.whiteSpace = 'pre-wrap';
  const cancel = document.createElement('button'); cancel.className='bsl-media-cancel'; cancel.type = 'button'; cancel.textContent = text('CANCEL');
  const insert = document.createElement('button'); insert.className='bsl-media-insert'; insert.type = 'button'; insert.textContent = text('INSERT'); insert.disabled = true;
  const formatting=document.createElement('details'); formatting.className='bsl-media-formatting';
  const summary=document.createElement('summary');summary.textContent=text('FORMAT');formatting.append(summary);
  const align=document.createElement('select');align.className='bsl-format-align';
  for(const [value,key] of [['none','ALIGN_NONE'],['center','ALIGN_CENTER'],['left','ALIGN_LEFT'],['right','ALIGN_RIGHT']]){
    const option=document.createElement('option');option.value=value;option.textContent=text(key);align.append(option);
  }
  const width=document.createElement('input');width.className='bsl-format-width';width.type='number';width.min='1';width.max='4096';width.step='1';width.placeholder=text('AUTO');
  const gap=document.createElement('input');gap.className='bsl-format-gap';gap.type='number';gap.min='0';gap.max='100';gap.step='1';gap.placeholder='16';
  for(const [key,control] of [['ALIGN',align],['WIDTH',width],['GAP',gap]]){
    const field=document.createElement('label');field.textContent=text(key);field.style.cssText='display:block;margin:.5rem 0;';
    control.style.cssText='display:block;max-width:100%;';field.append(control);formatting.append(field);
  }
  const help=document.createElement('p');help.textContent=text('MOBILE_HELP');formatting.append(help);
  const layoutError=document.createElement('p');layoutError.setAttribute('role','status');formatting.append(layoutError);
  let result, prepared;
  const updatePreview=()=>{
    try{
      if(width.validity?.badInput || gap.validity?.badInput)throw new Error('LAYOUT_INVALID');
      prepared=result?formatShortcode(result.shortcode,{align:align.value,width:width.value,gap:gap.value}):null;
      preview.textContent=prepared||'';insert.disabled=!prepared;layoutError.textContent='';
    }catch{prepared=null;preview.textContent='';insert.disabled=true;layoutError.textContent=text('LAYOUT_INVALID');}
  };
  for(const field of [align,width,gap])for(const event of ['input','change'])field.addEventListener(event,updatePreview);
  input.addEventListener('input', () => {
    try {
      result = parseInput(input.value); updatePreview();
      status.textContent = result.provider + (result.parametersRemoved ? ': ' + text('REMOVED') : '');
    } catch {
      result = null; updatePreview(); status.textContent = input.value ? text('INVALID') : '';
    }
  });
  const endpoint=Joomla.getOptions?.('bslmedia.library')?.url;
  let picker;
  const tabs=document.createElement('div');
  if(endpoint) {
    picker=createLibraryPicker({url:endpoint,text,
      select(item){result=item;updatePreview();},
      status(message){status.textContent=message;}});
    const files=document.createElement('button'), links=document.createElement('button');
    files.type=links.type='button';files.textContent=text('FILES');links.textContent=text('LINKS');
    const switchPane=local=>{
      label.hidden=local;picker.pane.hidden=!local;result=null;updatePreview();status.textContent='';
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
    if (!prepared) return;
    try {
      if (bookmark) { raw.focus(); raw.selection.moveToBookmark(bookmark); }
      if (range) { textarea.focus(); textarea.setSelectionRange(...range); }
      editor.replaceSelection(prepared); insert.disabled = true; result = null; prepared=null; close();
    }
    catch { status.textContent = text('INSERT_ERROR'); }
  });
  dialog.addEventListener('close', () => {picker?.stop();dialog.remove();}, { once: true });
  dialog.append(heading,tabs,label);if(picker)dialog.append(picker.pane);
  dialog.append(status,preview,formatting,cancel,insert); dialog.setAttribute('aria-label', 'BSL Media');
  document.body.append(dialog); dialog.showModal(); if(picker)picker.load();else input.focus();
}
JoomlaEditorButton.registerAction('bsl-media', (editor, options = {}) => {
  const target = options.editor ? JoomlaEditor.get(options.editor) : editor;
  if (!target) throw new Error('Editor unavailable');
  openMediaDialog(target);
});
