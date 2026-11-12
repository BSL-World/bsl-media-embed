// Canonical shortcode options only. No HTML or arbitrary CSS is generated.
export function formatShortcode(shortcode, {align = 'none', width = '', gap = ''} = {}) {
  if (!/^\{bsl(?:audio|video|yandexmusic|youtube)\s+[^{}]+\}$/.test(shortcode)
      || !['none', 'center', 'left', 'right'].includes(align)) throw new Error('LAYOUT_INVALID');
  width = String(width).trim(); gap = String(gap).trim();
  if ((width !== '' && (!/^[0-9]{1,4}$/.test(width) || Number(width) < 1 || Number(width) > 4096))
      || (gap !== '' && (!/^[0-9]{1,3}$/.test(gap) || Number(gap) > 100))) throw new Error('LAYOUT_INVALID');
  const attrs=[];
  if (align !== 'none' || width !== '' || gap !== '') attrs.push(`align="${align}"`);
  if (width !== '') attrs.push(`width="${Number(width)}"`);
  if (gap !== '') attrs.push(`gap="${Number(gap)}"`);
  return attrs.length ? shortcode.slice(0,-1)+' '+attrs.join(' ')+'}' : shortcode;
}
