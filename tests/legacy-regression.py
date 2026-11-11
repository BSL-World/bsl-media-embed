"""Differential local shortcode rendering against the actual v0.2.0 source, not a reimplementation."""
from pathlib import Path
import json, subprocess, tempfile

root = Path(__file__).resolve().parents[1]
baseline = subprocess.check_output(['git', 'show', 'v0.2.0:plg_content_bslmediaembed/src/Extension/BslMediaEmbed.php'], cwd=root)
stubs = (root / 'tests/shortcode-rendering.test.php').read_text(encoding='utf-8').split('namespace {', 1)[0]
runner = stubs + '''namespace {
define('_JEXEC', 1);
require $argv[1];
$results = [];
foreach (json_decode(file_get_contents($argv[2]), true) as $input) {
    $item = (object) ['text' => $input];
    $plugin = new \\BSL\\Plugin\\Content\\BslMediaEmbed\\Extension\\BslMediaEmbed();
    $plugin->replaceMediaShortcodes(new \\Joomla\\CMS\\Event\\Content\\ContentPrepareEvent($item));
    $results[] = $item->text;
}
echo json_encode($results, JSON_THROW_ON_ERROR);
}
'''
cases = []
for shortcode in ['bslaudio', 'bslvideo', 'BSLAUDIO', 'BSLVIDEO']:
    for key in ['key', 'A_b-c.1', 'x' * 128, 'x' * 129, '../bad', '', ' bad ']:
        for dimensions in ['', ' width="1" height="4096"', ' width="4097" height="0"', ' width="001" height="-1"',
                           ' width="50%" height="auto"', ' width="480" width="240" unknown="kept"']:
            cases.append(f'Before {{{shortcode} file="{key}"{dimensions}}} After')
cases += ['{bslaudio file=key}', "{bslvideo file='key'}", '{bslaudio file="key" junk}',
          '{bslaudio file="a"} {bslvideo file="b"}', '{bslvideo file="key" width="9999999999999999999999"}']
with tempfile.TemporaryDirectory() as temp:
    folder = Path(temp)
    (folder / 'old.php').write_bytes(baseline)
    (folder / 'runner.php').write_text(runner, encoding='utf-8')
    (folder / 'cases.json').write_text(json.dumps(cases), encoding='utf-8')
    for version in ['8.1', '8.2', '8.4']:
        php = Path(r'C:\OSPanel\modules') / ('PHP-' + version) / 'php.exe'
        def render(source):
            return subprocess.check_output([str(php), str(folder / 'runner.php'), str(source), str(folder / 'cases.json')])
        old = json.loads(render(folder / 'old.php'))
        new = json.loads(render(root / 'plg_content_bslmediaembed/src/Extension/BslMediaEmbed.php'))
        for index, (before, after) in enumerate(zip(old, new, strict=True)):
            before = before.replace('/joomla/download.php?file=', '/joomla/index.php?option=com_bslmedia&amp;task=media.stream&amp;file=').replace('&amp;source=site', '')
            if before != after: raise AssertionError(f'Local regression on PHP {version}: {cases[index]}')
        print(f'PASS PHP {version}: {len(cases)} cases equivalent to v0.2.0 except documented endpoint migration')
