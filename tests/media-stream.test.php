<?php
declare(strict_types=1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
use BSL\Component\Bslmedia\Site\Service\MediaStream as Stream;
$dir=__DIR__.'/../dist/stream-unit-'.getmypid();mkdir($dir,0700,true);
file_put_contents($dir.'/sample.mp3','0123456789');file_put_contents($dir.'/empty.mp4','');
$map=['song'=>'sample.mp3','empty'=>'empty.mp4','bad'=>'../outside.mp3','doc'=>'secret.pdf','abs'=>'C:/secret.mp3','encoded'=>'%2e%2e/a.mp3','slash'=>'a\\b.mp3','missing'=>'missing.mp3'];
$count=0;
function check($value,$expected):void {global $count;if($value!==$expected)throw new RuntimeException('Mismatch: '.var_export([$value,$expected],true));$count++;}
function request(string $key='song',string $method='GET',?string $range=null):array {global $dir,$map;return Stream::open($dir,$map,$key,$method,$range);}
function body(array $r):string {ob_start();Stream::emitBody($r);return ob_get_clean();}
try {
 $r=request();check($r['status'],200);check($r['headers']['Content-Length'],'10');check($r['headers']['Content-Type'],'audio/mpeg');check(body($r),'0123456789');
 foreach(['bytes=2-5'=>['2345','bytes 2-5/10'],'bytes=7-'=>['789','bytes 7-9/10'],'bytes=-3'=>['789','bytes 7-9/10'],'bytes=0-99'=>['0123456789','bytes 0-9/10']] as $range=>$expected){$r=request(range:$range);check($r['status'],206);check($r['headers']['Content-Range'],$expected[1]);check($r['headers']['Content-Length'],(string)strlen($expected[0]));check(body($r),$expected[0]);}
 foreach(['bytes=10-','bytes=5-2','bytes=-0','bytes=0-1,4-5','garbage','bytes=999999999999999999999999-'] as $range){$r=request(range:$range);check($r['status'],416);check($r['headers']['Content-Range'],'bytes */10');check(body($r),'');}
 $r=request(method:'HEAD',range:'bytes=1-3');check($r['status'],200);check($r['headers']['Content-Length'],'10');check(body($r),'');
 check(request(method:'POST')['status'],405);
 foreach(['../x','/etc/passwd','a%2fb','a\\b','..','a..b'] as $key)check(request($key)['status'],400);
 foreach(['bad','doc','abs','encoded','slash'] as $key)check(request($key)['status'],403);
 check(request('missing')['status'],404);check(request('unlisted')['status'],404);
 check(Stream::resolveRoot('.', $dir),realpath($dir));
 mkdir($dir.'/site');check(Stream::resolveRoot('..',$dir.'/site'),realpath($dir));rmdir($dir.'/site');
 check(Stream::resolveRoot('php://filter',$dir),'');
 $auto=Stream::open($dir,[],'sample','GET',null);check($auto['status'],200);check(body($auto),'0123456789');
 $auto=Stream::open($dir,[],'sample.mp3','HEAD',null);check($auto['status'],200);check(body($auto),'');
 file_put_contents($dir.'/sample.opus','Opus fixture');
 check(Stream::open($dir,[],'sample','GET',null)['status'],409);
 $auto=Stream::open($dir,[],'sample.opus','GET','bytes=0-3');check($auto['status'],206);check($auto['headers']['Content-Type'],'audio/ogg; codecs=opus');check(body($auto),'Opus');
 file_put_contents($dir.'/private.pdf','secret');check(Stream::open($dir,[],'private.pdf','GET',null)['status'],404);
 unlink($dir.'/private.pdf');unlink($dir.'/sample.opus');
 $unicode='Песня с пробелом.opus';file_put_contents($dir.'/'.$unicode,'Opus');
 $catalog=Stream::catalog($dir);$ukey=array_search($unicode,$catalog,true);check(is_string($ukey),true);
 $auto=Stream::open($dir,[],$ukey,'GET',null);check($auto['status'],200);check(body($auto),'Opus');
 check(str_contains($auto['headers']['Content-Disposition'],rawurlencode($unicode)),true);unlink($dir.'/'.$unicode);
 check(Stream::open('',[],'song','GET',null)['status'],503);
 $r=request('empty');check($r['headers']['Content-Length'],'0');check(body($r),'');check(request('empty',range:'bytes=0-')['status'],416);
 $outside=$dir.'-outside.mp3';file_put_contents($outside,'private');
 if(@symlink($outside,$dir.'/link.mp3')){$map['link']='link.mp3';check(request('link')['status'],403);unlink($dir.'/link.mp3');echo "PASS symlink escape\n";}else echo "SKIP symlink creation unavailable on this host\n";
 unlink($outside);
 echo "PASS $count media request/path/header/body assertions (no HTTP server)\n";
}finally{unlink($dir.'/sample.mp3');unlink($dir.'/empty.mp4');rmdir($dir);}
