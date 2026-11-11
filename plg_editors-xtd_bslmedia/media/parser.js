// No network access. Kept independent of the editor for regression tests.
const fail = () => { throw new Error('INVALID'); };
export function parseUrl(input, iframe = false) {
  if (input.length > 4096 || /[\s\\\u0000-\u001f\u007f]/u.test(input)) fail();
  const raw = /^https:\/\/([a-z0-9.-]+)(\/[^?#]*)?(?:\?([^#]*))?$/i.exec(input);
  if (!raw || /%|(?:^|\/)\.{1,2}(?:\/|$)/.test(raw[2] || '')) fail();
  const url = new URL(input), path = raw[2] || '/';
  const entries = [...url.searchParams], keys = new Set();
  for (const [key] of entries) { if (keys.has(key)) fail(); keys.add(key); }
  let match, shortcode, provider;
  if (url.hostname === 'music.yandex.ru') {
    match = (iframe ? /^\/iframe\/album\/([1-9][0-9]{0,19})\/track\/([1-9][0-9]{0,19})$/ : /^\/album\/([1-9][0-9]{0,19})\/track\/([1-9][0-9]{0,19})$/).exec(path);
    if (!match || entries.some(([k]) => !/^utm_[a-z_]+$/.test(k))) fail();
    shortcode = `{bslyandexmusic album="${match[1]}" track="${match[2]}"}`;
    provider = 'Yandex Music';
  } else {
    let id;
    if (!iframe && url.hostname === 'youtu.be') id = /^\/([^/]+)$/.exec(path)?.[1];
    else if (['youtube.com', 'www.youtube.com'].includes(url.hostname)) {
      if (!iframe && path === '/watch') id = url.searchParams.get('v');
      else id = (iframe ? /^\/embed\/([^/]+)$/ : /^\/(?:embed|shorts)\/([^/]+)$/).exec(path)?.[1];
    } else if (iframe && url.hostname === 'www.youtube-nocookie.com') id = /^\/embed\/([^/]+)$/.exec(path)?.[1];
    if (!id || !/^[A-Za-z0-9_-]{11}$/.test(id)) fail();
    if (entries.some(([k, v]) => !(['v', 'si', 't', 'start'].includes(k) || /^utm_[a-z_]+$/.test(k))
      || (k === 'v' && (path !== '/watch' || v !== id)))) fail();
    shortcode = `{bslyoutube video="${id}"}`;
    provider = 'YouTube';
  }
  return { shortcode, provider, parametersRemoved: entries.some(([k]) => ['t', 'start'].includes(k)) };
}

export function parseInput(value, createDocument = () => document.implementation.createHTMLDocument('')) {
  const input = value.trim();
  if (!input || input.length > 32768) fail();
  if (!input.startsWith('<')) return parseUrl(input);
  const envelope = /^<iframe\s+([^<>]+)>([\s\S]*)<\/iframe>$/i.exec(input);
  if (!envelope) fail();
  let rest = envelope[1], names = new Set();
  while (rest.trim()) {
    const attr = /^\s*([a-z][a-z0-9-]*)\s*(?:=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]+))?(?=\s|$)/i.exec(rest);
    if (!attr || names.has(attr[1].toLowerCase())) fail();
    names.add(attr[1].toLowerCase()); rest = rest.slice(attr[0].length);
  }
  // Templates are inert: iframe/links are never attached, navigated or executed.
  const doc = createDocument(), template = doc.createElement('template');
  template.innerHTML = input;
  const nodes = [...template.content.childNodes].filter(n => n.nodeType !== 3 || n.textContent.trim());
  if (nodes.length !== 1 || nodes[0].nodeName !== 'IFRAME') fail();
  const frame = nodes[0];
  const allowed = new Set(['src', 'width', 'height', 'style', 'frameborder', 'allow', 'allowfullscreen', 'title', 'loading', 'referrerpolicy']);
  if ([...frame.attributes].some(a => !allowed.has(a.name))) fail();
  const body = doc.createElement('template');
  body.innerHTML = frame.textContent;
  if ([...body.content.querySelectorAll('*')].some(el => el.tagName !== 'A'
      || [...el.attributes].some(a => a.name !== 'href'))) fail();
  for (const link of body.content.querySelectorAll('a')) {
    if (!/^https:\/\/music\.yandex\.ru\/(?:artist\/[1-9][0-9]{0,19}|album\/[1-9][0-9]{0,19}\/track\/[1-9][0-9]{0,19})(?:\?utm_source=web&utm_medium=copy_link)?$/.test(link.getAttribute('href') || '')) fail();
  }
  return parseUrl(frame.getAttribute('src') || '', true);
}
