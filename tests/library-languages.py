from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
used=set()
for path in (root/'com_bslmedia').rglob('*'):
 if path.suffix not in ('.php','.xml'):continue
 text=path.read_text(encoding='utf-8')
 used.update(re.findall(r'COM_BSLMEDIA_[A-Z_]+',text))
 used.update('COM_BSLMEDIA_'+s for s in re.findall(r"\$t\('([A-Z_]+)'\)",text))
used.update('COM_BSLMEDIA_'+s for s in ['FILE','FOLDER','TYPE','SIZE','KEY','SHORTCODE'])
used.update('COM_BSLMEDIA_'+s for s in ['WITHIN_SITE','BESIDE_SITE','SITE_CONTAINER','ROOT_NOT_CHOSEN','ROOT_RESELECT'])
for locale in ['ru-RU','en-GB']:
 text=(root/'com_bslmedia/admin/language'/locale/'com_bslmedia.ini').read_text(encoding='utf-8')
 keys=re.findall(r'^(COM_BSLMEDIA_[A-Z_]+)=',text,re.M)
 assert len(keys)==len(set(keys)),f'Duplicate language keys in {locale}'
 assert used<=set(keys),f'Missing language keys in {locale}: {used-set(keys)}'
print(f'PASS {len(used)} referenced component language keys in ru-RU/en-GB')
button=(root/'plg_editors-xtd_bslmedia/src/Extension/BslMedia.php').read_text(encoding='utf-8')
registered=re.search(r'foreach\s*\(\[(.*?)\]\s*as \$key\)',button,re.S)
assert registered,'Editor translations are not registered'
editor_used={'PLG_EDITORSXTD_BSLMEDIA_'+key for key in re.findall(r"'([A-Z_]+)'",registered[1])}
for locale in ['ru-RU','en-GB']:
 text=(root/'plg_editors-xtd_bslmedia/language'/locale/'plg_editors-xtd_bslmedia.ini').read_text(encoding='utf-8')
 keys=re.findall(r'^(PLG_EDITORSXTD_BSLMEDIA_[A-Z_]+)=',text,re.M)
 assert len(keys)==len(set(keys)),f'Duplicate editor keys in {locale}'
 assert editor_used<=set(keys),f'Missing editor keys in {locale}: {editor_used-set(keys)}'
print(f'PASS {len(editor_used)} registered editor language keys in ru-RU/en-GB')
