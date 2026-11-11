import test from 'node:test';
import assert from 'node:assert/strict';
import { parseUrl } from '../plg_editors-xtd_bslmedia/media/parser.js';

test('share links become canonical shortcodes', () => {
  assert.equal(parseUrl('https://music.yandex.ru/album/23303538/track/91309875?utm_source=web&utm_medium=copy_link').shortcode,
    '{bslyandexmusic album="23303538" track="91309875"}');
  assert.equal(parseUrl('https://youtu.be/ZyBl3noTNSs').shortcode, '{bslyoutube video="ZyBl3noTNSs"}');
  for (const url of ['https://youtube.com/watch?v=ZyBl3noTNSs', 'https://www.youtube.com/shorts/ZyBl3noTNSs', 'https://youtube.com/embed/ZyBl3noTNSs']) {
    assert.equal(parseUrl(url).shortcode, '{bslyoutube video="ZyBl3noTNSs"}');
  }
  assert.equal(parseUrl('https://music.yandex.ru/iframe/album/3513/track/42612', true).shortcode,
    '{bslyandexmusic album="3513" track="42612"}');
  assert.equal(parseUrl('https://www.youtube-nocookie.com/embed/ZyBl3noTNSs', true).provider, 'YouTube');
  assert.equal(parseUrl('https://youtu.be/ZyBl3noTNSs?t=20&si=tracking').parametersRemoved, true);
});

test('host, protocol, path and parameter attacks are rejected', () => {
  for (const input of [
    'http://youtu.be/ZyBl3noTNSs', 'javascript:alert(1)', '//youtu.be/ZyBl3noTNSs',
    'https://youtu.be.evil.test/ZyBl3noTNSs', 'https://user@youtu.be/ZyBl3noTNSs',
    'https://youtu.be:443/ZyBl3noTNSs', 'https://youtu.be\\@evil.test/ZyBl3noTNSs',
    'https://youtube.com/a/../watch?v=ZyBl3noTNSs', 'https://youtu.be/%5AyBl3noTNSs',
    'https://youtu.be/ZyBl3noTNSs#x', 'https://youtube.com/watch?v=ZyBl3noTNSs&v=AAAAAAAAAAA',
    'https://youtube.com/watch?v=ZyBl3noTNSs&list=foo', 'https://youtu.be/short',
    'https://music.yandex.ru/album/0/track/42612', 'https://music.yandex.ru/album/01/track/42612',
    'https://music.yandex.ru/album/3513/track/42612?src=evil',
    'https://music.yandex.ru/album/123456789012345678901/track/42612',
  ]) assert.throws(() => parseUrl(input), undefined, input);
});
