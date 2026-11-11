"""Deterministic Joomla ZIPs: python tools/build.py [output-directory]."""
import hashlib
import io
from pathlib import Path
import sys
import xml.etree.ElementTree as ET
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]

def archive(entries):
    stream = io.BytesIO()
    with ZipFile(stream, 'w', compression=ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(entries.items()):
            entry = ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
            entry.compress_type = ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            z.writestr(entry, data, compresslevel=9)
    return stream.getvalue()

def sources(folder, manifest_name):
    """Only manifest-declared payloads; do not ship unrelated root files or editor backups."""
    manifest = ET.parse(folder / manifest_name).getroot()
    selected = {folder / manifest_name}
    for section in ['files', 'languages', 'media', 'administration/files', 'administration/languages']:
        entries = manifest.find(section)
        if entries is None:
            continue
        prefix = entries.get('folder', '')
        for entry in entries:
            path = folder / prefix / entry.text
            if not path.resolve().is_relative_to(folder.resolve()):
                raise ValueError(f'Manifest path escapes plugin root: {entry.text}')
            if entry.tag == 'folder':
                if not path.is_dir(): raise ValueError(f'Missing manifest folder: {path}')
                selected.update(p for p in path.rglob('*') if p.is_file())
            else:
                selected.add(path)
    if manifest.findtext('scriptfile'):
        selected.add(folder / manifest.findtext('scriptfile'))
    entries = {}
    text_types = {'.php', '.js', '.css', '.ini', '.xml', '.md', '.txt', '.svg', '.json'}
    allowed = text_types | {'.png', '.jpg', '.jpeg', '.webp', '.gif', '.woff', '.woff2'}
    for path in sorted(selected):
        if path.is_symlink() or not path.resolve().is_relative_to(folder.resolve()):
            raise ValueError(f'Unsafe payload path: {path}')
        if any(part.startswith('.') for part in path.relative_to(folder).parts) or path.suffix.lower() not in allowed:
            raise ValueError(f'Unexpected payload file: {path}')
        data = path.read_bytes()
        entries[path.relative_to(folder).as_posix()] = data.replace(b'\r\n', b'\n') if path.suffix.lower() in text_types else data
    return entries

def build():
    output = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / 'dist'
    output.mkdir(parents=True, exist_ok=True)
    manifest = (ROOT / 'package/pkg_bslmediaembed.xml').read_bytes()
    version = ET.fromstring(manifest).findtext('version')
    entries = {'pkg_bslmediaembed.xml': manifest.replace(b'\r\n', b'\n')}
    for folder, name in [('com_bslmedia', 'bslmedia.xml'), ('plg_content_bslmediaembed', 'bslmediaembed.xml'), ('plg_editors-xtd_bslmedia', 'bslmedia.xml')]:
        data = sources(ROOT / folder, name)
        if ET.fromstring(data[name]).findtext('version') != version:
            raise ValueError(f'Package/plugin version mismatch: {folder}')
        blob = archive(data)
        entries[f'packages/{folder}.zip'] = blob
        (output / f'{folder}-{version}.zip').write_bytes(blob)
    blob = archive(entries)
    target = output / f'pkg_bslmediaembed-{version}.zip'
    target.write_bytes(blob)
    checksum = hashlib.sha256(blob).hexdigest()
    target.with_suffix('.sha256').write_text(f'{checksum}  {target.name}\n', encoding='ascii')
    feed = f'''<?xml version="1.0" encoding="UTF-8"?>
<updates><update>
<name>BSL Media Embed Package</name><element>pkg_bslmediaembed</element><type>package</type>
<version>{version}</version><client>site</client>
<downloads><downloadurl type="full" format="zip">https://bsl-world.ru/download.php?file=bsl-media-embed-package-{version}&amp;download=1&amp;source=joomla</downloadurl></downloads>
<targetplatform name="joomla" version="[56]\\.[0-9]+"/><php_minimum>8.1.0</php_minimum>
<sha256>{checksum}</sha256>
</update></updates>
'''
    (output / 'package-updates.xml').write_text(feed, encoding='utf-8', newline='\n')
    print(f'{target}\nSHA-256 {checksum}')

if __name__ == '__main__':
    build()
