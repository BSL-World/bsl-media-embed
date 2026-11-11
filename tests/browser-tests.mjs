import { parseInput } from '../plg_editors-xtd_bslmedia/media/parser.js';
window.Joomla = { Text: { _: key => key } };
await import('../plg_editors-xtd_bslmedia/media/button.js?bsl-review=20261109');
const result = document.querySelector('#result');
let checks = 0;
const check = (value, label) => { if (!value) throw new Error(label); checks++; };
const rejects = value => { let failed = false; try { parseInput(value); } catch { failed = true; } check(failed, value); };
try {
  check(performance.getEntriesByType('resource').some(entry => entry.name.endsWith('/parser.js?bsl-review=20261109')), 'parser received asset cache version');
  const official = '<iframe frameborder="0" allow="clipboard-write" style="border:none;width:100%;height:122px;" width="100%" height="122" src="https://music.yandex.ru/iframe/album/3513/track/42612">Слушайте <a href="https://music.yandex.ru/album/3513/track/42612?utm_source=web&utm_medium=copy_link">Until The End</a> — <a href="https://music.yandex.ru/artist/13462">E-Type</a> на Яндекс Музыке</iframe>';
  check(parseInput(official).shortcode === '{bslyandexmusic album="3513" track="42612"}', 'official Yandex');
  check(parseInput('<iframe src="https://www.youtube.com/embed/ZyBl3noTNSs" title="YouTube video player" allowfullscreen></iframe>').shortcode === '{bslyoutube video="ZyBl3noTNSs"}', 'official YouTube');
  for (const bad of [
    '', '{source}' + official + '{/source}', '&lt;iframe&gt;',
    official + official, '<div>' + official + '</div>', official + '<script>window.pwned=1</script>',
    official.replace('frameborder="0"', 'onload="window.pwned=1"'),
    official.replace('frameborder="0"', 'srcdoc="bad"'),
    official.replace('frameborder="0"', 'src="https://evil.test"'),
    official.replace('frameborder="0"', 'allow="a" ALLOW="b"'),
    official.replace('Until The End', '<img src="https://evil.test/x" onerror="window.pwned=1">'),
    official.replace('https://music.yandex.ru/artist/13462', 'javascript:alert(1)'),
    official.replace('/iframe/album/3513/track/42612', '/iframe/album/0/track/42612'),
    '<iframe src="https://www.youtube.com/embed/ZyBl3noTNSs"><iframe src="https://evil.test"></iframe></iframe>',
  ]) rejects(bad);
  check(!window.pwned && !document.querySelector('iframe'), 'input HTML stayed inert');
  let inserted = [];
  let bookmarkRestored = false;
  const editor = {
    getRawInstance: () => ({ focus() {}, selection: {
      getBookmark: () => ({ position: 2 }), moveToBookmark: mark => { bookmarkRestored = mark.position === 2; },
    } }),
    replaceSelection: value => inserted.push(value),
  };
  window.testEditors = { article: editor };
  window.mediaActions['bsl-media']({ replaceSelection() { throw new Error('Wrong editor'); } }, { editor: 'article' });
  let dialog = document.querySelector('dialog'), input = dialog.querySelector('textarea');
  const buttons = dialog.querySelectorAll('button');
  check(dialog.open && buttons[1].disabled, 'empty dialog');
  input.value = '<img src=x onerror=alert(1)>'; input.dispatchEvent(new Event('input'));
  check(buttons[1].disabled, 'invalid input disabled');
  input.value = official; input.dispatchEvent(new Event('input'));
  check(!buttons[1].disabled && dialog.querySelector('pre').textContent.includes('3513'), 'preview');
  buttons[1].click();
  buttons[1].click();
  check(inserted.length === 1 && inserted[0] === '{bslyandexmusic album="3513" track="42612"}', 'shortcode only');
  check(bookmarkRestored, 'selection bookmark restored');
  check(!document.querySelector('dialog'), 'closed after insertion');
  window.mediaActions['bsl-media']({ replaceSelection: () => { throw new Error('wrong editor'); } });
  dialog = document.querySelector('dialog');
  dialog.querySelector('button').click();
  check(inserted.length === 1 && !document.querySelector('dialog'), 'cancel does not insert');
  let sourceSelection;
  const sourceTextarea = { selectionStart: 3, selectionEnd: 7, focus() {}, setSelectionRange(...args) { sourceSelection = args; } };
  const sourceEditor = { getRawInstance: () => ({ isHidden: () => true, getElement: () => sourceTextarea,
    selection: { getBookmark() { throw new Error('Visual selection must not be accessed in source mode'); } } }),
    replaceSelection: value => inserted.push(value) };
  window.mediaActions['bsl-media'](sourceEditor);
  dialog = document.querySelector('dialog');
  check(dialog.open, 'source-mode dialog opened');
  input = dialog.querySelector('textarea'); input.value = 'https://youtu.be/ZyBl3noTNSs'; input.dispatchEvent(new Event('input'));
  dialog.querySelectorAll('button')[1].click();
  check(JSON.stringify(sourceSelection) === '[3,7]' && inserted[1] === '{bslyoutube video="ZyBl3noTNSs"}', 'source textarea range and shortcode');
  window.mediaActions['bsl-media']({ getRawInstance: () => ({ selection: { getBookmark() { throw new Error('Unavailable'); } } }),
    replaceSelection: value => inserted.push(value) });
  dialog = document.querySelector('dialog');
  check(dialog.open, 'unavailable bookmark does not prevent opening');
  input = dialog.querySelector('textarea'); input.value = 'https://youtu.be/ZyBl3noTNSs'; input.dispatchEvent(new Event('input'));
  dialog.querySelectorAll('button')[1].click();
  check(inserted.length === 3, 'provider insertion works without a bookmark');
  window.mediaActions['bsl-media']({ replaceSelection() { throw new Error('Editor insertion failed'); } });
  dialog = document.querySelector('dialog');
  input = dialog.querySelector('textarea'); input.value = 'https://youtu.be/ZyBl3noTNSs'; input.dispatchEvent(new Event('input'));
  dialog.querySelectorAll('button')[1].click();
  check(dialog.open && dialog.querySelector('p').textContent.endsWith('INSERT_ERROR'), 'insertion error is shown without closing');
  dialog.querySelector('button').click();
  check(inserted.length === 3, 'failed insertion and cancel preserve content');
  window.mediaActions['bsl-media'](editor);
  dialog = document.querySelector('dialog');
  dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
  check(!document.querySelector('dialog') && inserted.length === 3, 'native cancel handler removes dialog without insertion');
  result.textContent = `PASS ${checks} checks (DOM/parser/dialog; mocked editor API, no Joomla)`;
} catch (error) { result.textContent = 'FAIL ' + error.stack; }
