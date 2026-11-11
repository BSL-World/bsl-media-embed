<?php
declare(strict_types=1);
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary as Library;
use BSL\Component\Bslmedia\Site\Service\MediaStream as Stream;
$dir=__DIR__.'/../dist/library-unit-'.getmypid();mkdir($dir,0700,true);$count=0;
function expect($a,$b):void{global $count;if($a!==$b)throw new RuntimeException('Mismatch: '.var_export([$a,$b],true));$count++;}
function denied(callable $fn,string $key):void{try{$fn();}catch(RuntimeException $e){expect($e->getMessage(),$key);return;}throw new RuntimeException('Expected rejection: '.$key);}
try{
 expect(Library::parseLimit('10','MB'),10000000);expect(Library::parseLimit('1.5','GB'),1500000000);
 expect(Library::parseLimit('0.000001','MB'),1);expect(Library::parseLimit('0.001','GB'),1000000);
 expect(Library::limit(['max_upload_mb'=>64])['bytes'],67108864);
 expect(Library::limit(['max_upload_mb'=>64])['value'],'67.108864');
 expect(Library::limit(['max_upload_value'=>'2','max_upload_unit'=>'GB','max_upload_mb'=>64])['bytes'],2000000000);
 foreach([['0','MB'],['-1','MB'],['1','MiB'],['1e6','MB'],['1.0000001','GB'],['9999999999','GB']] as [$v,$u])denied(fn()=>Library::parseLimit($v,$u),'COM_BSLMEDIA_BAD_LIMIT');
 foreach(['../x','/x','a/b','a\\b','%2e',' x','x.','CON','NUL.mp3',"a\n","bad/\xff.mp3"] as $bad)expect(Library::name($bad),false);
 expect(Library::name('Музыка'),true);
 Library::createFolder($dir,'','Music');Library::createFolder($dir,'Music','Album');
 expect(Library::folders($dir),['Music']);expect(Library::folders($dir,'Music'),['Music/Album']);
 denied(fn()=>Library::createFolder($dir,'Music/Album','Third'),'COM_BSLMEDIA_DEPTH');
 denied(fn()=>Library::directory($dir,'../outside'),'COM_BSLMEDIA_BAD_PATH');
 denied(fn()=>Library::createFolder($dir,'','Music'),'COM_BSLMEDIA_EXISTS');
 file_put_contents($dir.'/bad.mp3','<?php echo "bad";');
 denied(fn()=>Library::upload($dir,'',$dir.'/bad.mp3','not-media.mp3',1000),'COM_BSLMEDIA_BAD_TYPE');
 denied(fn()=>Library::upload($dir,'',$dir.'/bad.mp3','file.php',1000),'COM_BSLMEDIA_BAD_TYPE');
 denied(fn()=>Library::upload($dir,'',$dir.'/bad.mp3','file.mp3',1),'COM_BSLMEDIA_TOO_LARGE');
 $mp4=pack('N',24).'ftypisom'.pack('N',0).'isommp42';file_put_contents($dir.'/source.bin',$mp4);
 expect((new finfo(FILEINFO_MIME_TYPE))->file($dir.'/source.bin'),'video/mp4');
 denied(fn()=>Library::upload($dir,'',$dir.'/source.bin','shell.php.mp4',1000),'COM_BSLMEDIA_BAD_TYPE');
 denied(fn()=>Library::upload($dir,'',$dir.'/source.bin','shell.phtml.mp4',1000),'COM_BSLMEDIA_BAD_TYPE');
 Library::upload($dir,'Music/Album',$dir.'/source.bin','клип.mp4',1000);
 expect(file_get_contents($dir.'/Music/Album/клип.mp4'),$mp4);
 denied(fn()=>Library::upload($dir,'Music/Album',$dir.'/source.bin','клип.mp4',1000),'COM_BSLMEDIA_EXISTS');
 expect(file_get_contents($dir.'/Music/Album/клип.mp4'),$mp4);expect(glob($dir.'/Music/Album/.bsl-upload-*'),[]);
 $catalog=Stream::catalog($dir);$key=array_search('Music/Album/клип.mp4',$catalog,true);expect(is_string($key),true);
 $r=Stream::open($dir,[],$key,'HEAD',null);expect($r['status'],200);expect($r['headers']['Content-Length'],(string)strlen($mp4));fclose($r['handle']);
 mkdir($dir.'/Music/Album/Third');file_put_contents($dir.'/Music/Album/Third/hidden.mp3','hidden');
 expect(in_array('Music/Album/Third/hidden.mp3',Stream::files($dir),true),false);
 denied(fn()=>Library::directory($dir,'Music/Album/Third'),'COM_BSLMEDIA_DEPTH');
 foreach(['delete','rename','move','overwrite'] as $method)expect(method_exists(Library::class,$method),false);
 echo "PASS $count media library assertions: depth, traversal, type, no overwrite, Unicode, delivery\n";
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $p){if($p->isDir()&&!$p->isLink())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($dir);
}
