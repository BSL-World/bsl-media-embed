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
for locale in ['ru-RU','en-GB']:
 text=(root/'com_bslmedia/admin/language'/locale/'com_bslmedia.ini').read_text(encoding='utf-8')
 keys=re.findall(r'^(COM_BSLMEDIA_[A-Z_]+)=',text,re.M)
 assert len(keys)==len(set(keys)),f'Duplicate language keys in {locale}'
 assert used<=set(keys),f'Missing language keys in {locale}: {used-set(keys)}'
print(f'PASS {len(used)} referenced component language keys in ru-RU/en-GB')
