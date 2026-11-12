import test from 'node:test';
import assert from 'node:assert/strict';
import {formatShortcode} from '../plg_editors-xtd_bslmedia/media/formatting.js';
test('formatting preserves defaults and adds only bounded canonical attributes to all player types',()=>{
 for(const shortcode of ['{bslaudio file="song"}','{bslvideo file="clip.mp4"}','{bslyoutube video="ZyBl3noTNSs"}','{bslyandexmusic album="3513" track="42612"}']){
  assert.equal(formatShortcode(shortcode),shortcode);
  assert.equal(formatShortcode(shortcode,{align:'none',width:'',gap:''}),shortcode);
  assert.equal(formatShortcode(shortcode,{align:'left',width:'0320',gap:'000'}),shortcode.slice(0,-1)+' align="left" width="320" gap="0"}');
  assert.equal(formatShortcode(shortcode,{align:'center'}),shortcode.slice(0,-1)+' align="center"}');
  assert.equal(formatShortcode(shortcode,{width:'320'}),shortcode.slice(0,-1)+' align="none" width="320"}');
 }
});
test('formatting rejects invalid layout values and noncanonical shortcode envelopes',()=>{
 for(const options of [{align:'top'},{align:'left;clear:both'},{width:'0'},{width:'4097'},{width:'50%'},{width:'1e3'},{width:'320.5'},{gap:'101'},{gap:'-1'},{gap:'0;float:right'}]){
  assert.throws(()=>formatShortcode('{bslvideo file="clip"}',options),/LAYOUT_INVALID/);
 }
 for(const input of ['<iframe src=x>','{unknown file="x"}','{bslaudio file="x"}{bslvideo file="y"}'])assert.throws(()=>formatShortcode(input),/LAYOUT_INVALID/);
 assert.equal(formatShortcode('{bslvideo file="clip"}',{width:'4096',gap:'100'}),'{bslvideo file="clip" align="none" width="4096" gap="100"}');
});
