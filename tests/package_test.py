"""Check deterministic archives, manifests, language/media paths and update checksum."""
import hashlib
import importlib.util
import io
from pathlib import Path
import sys
import shutil
import tempfile
import xml.etree.ElementTree as ET
from zipfile import ZipFile

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('build', root / 'tools/build.py')
build = importlib.util.module_from_spec(spec)
spec.loader.exec_module(build)
with tempfile.TemporaryDirectory() as temp:
    copied_plugin = Path(temp) / 'copied-plugin'
    shutil.copytree(root / 'plg_editors-xtd_bslmedia', copied_plugin)
    (copied_plugin / 'unlisted-private.txt').write_text('not for distribution')
    payload = build.sources(copied_plugin, 'bslmedia.xml')
    assert 'unlisted-private.txt' not in payload, 'Unlisted plugin root file was packaged'
    (copied_plugin / 'services/private.php.bak').write_text('not for distribution')
    try:
        build.sources(copied_plugin, 'bslmedia.xml')
        raise AssertionError('Editor backup in declared source folder was accepted')
    except ValueError:
        pass
    sys.argv = ['build.py', temp]
    build.build()
    version = ET.parse(root / 'package/pkg_bslmediaembed.xml').getroot().findtext('version')
    target = Path(temp) / f'pkg_bslmediaembed-{version}.zip'
    first = target.read_bytes()
    build.build()
    assert first == target.read_bytes(), 'Non-deterministic build'
    feed = ET.parse(Path(temp) / 'package-updates.xml').getroot().find('update')
    assert feed.findtext('sha256') == hashlib.sha256(first).hexdigest()
    assert feed.findtext('element') == 'pkg_bslmediaembed'
    with ZipFile(io.BytesIO(first)) as package:
        assert set(package.namelist()) == {'pkg_bslmediaembed.xml', 'packages/com_bslmedia.zip', 'packages/plg_content_bslmediaembed.zip', 'packages/plg_editors-xtd_bslmedia.zip'}
        manifest = ET.fromstring(package.read('pkg_bslmediaembed.xml'))
        assert manifest.findtext('creationDate') == ET.parse(root / 'package/pkg_bslmediaembed.xml').getroot().findtext('creationDate')
        for child in manifest.find('files'):
            with ZipFile(io.BytesIO(package.read('packages/' + child.text))) as plugin:
                manifest_name = 'bslmediaembed.xml' if child.attrib.get('group') == 'content' else 'bslmedia.xml'
                plugin_manifest = ET.fromstring(plugin.read(manifest_name))
                assert plugin_manifest.attrib.get('group') == child.attrib.get('group')
                assert plugin_manifest.findtext('version') == version
                assert plugin_manifest.findtext('creationDate') == manifest.findtext('creationDate')
                for entry in plugin_manifest.find('files'):
                    prefix = plugin_manifest.find('files').get('folder', '')
                    if entry.tag == 'folder': assert any(n.startswith((prefix + '/' if prefix else '') + entry.text + '/') for n in plugin.namelist())
                    else: assert entry.text in plugin.namelist()
                for entry in plugin_manifest.findall('languages/language'): assert entry.text in plugin.namelist()
                for section in ['administration/files', 'administration/languages']:
                    block = plugin_manifest.find(section)
                    if block is not None:
                        for entry in block:
                            name = block.get('folder', '') + '/' + entry.text
                            if entry.tag == 'folder': assert any(n.startswith(name + '/') for n in plugin.namelist())
                            else: assert name in plugin.namelist()
                media = plugin_manifest.find('media')
                if media is not None:
                    for entry in media: assert 'media/' + entry.text in plugin.namelist()
                assert not any(n.startswith(('tests/', '.git/', 'update-server/')) for n in plugin.namelist())
print('OK: manifest-only payload, backup rejection, deterministic package, child identities and feed SHA-256')
