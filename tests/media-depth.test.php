<?php
declare(strict_types=1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/site/src/Service/EditorLibrary.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
use BSL\Component\Bslmedia\Site\Service\MediaStream as Stream;
use BSL\Component\Bslmedia\Site\Service\EditorLibrary;
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary as Library;
$root=__DIR__.'/../dist/depth-'.getmypid();mkdir($root);
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
 $relative='';
 for($i=0;$i<32;$i++){Library::createFolder($root,$relative,'d');$relative.=($relative===''?'':'/').'d';}
 file_put_contents($root.'/'.$relative.'/edge.mp3','012345');
 $key='media-'.substr(hash('sha256',$relative.'/edge.mp3'),0,32);
 verify(Stream::catalog($root)[$key]===$relative.'/edge.mp3','Depth-32 catalogue with stable key');
 $r=Stream::open($root,[],$key,'GET','bytes=2-4');verify($r['status']===206,'Depth-32 Range');ob_start();Stream::emitBody($r);verify(ob_get_clean()==='234','Depth-32 stream body');
 verify(EditorLibrary::page($root,'edge')['total']===1,'Editor discovers the same deep file');
 verify(Stream::scan($root)['truncated']===false,'Complete scan reported');
 mkdir($root.'/'.$relative.'/extra');file_put_contents($root.'/'.$relative.'/extra/private.mp3','deeper');
 $scan=Stream::scan($root);verify($scan['truncated']===true,'Depth limit explicitly reported');
 verify(!in_array($relative.'/extra/private.mp3',$scan['files'],true),'Depth-33 not scanned');
 verify(EditorLibrary::page($root)['truncated']===true,'Editor receives incomplete catalogue warning');
 verify(Stream::open($root,[],'unknown.mp3','HEAD',null)['status']===503,'Incomplete catalogue is not a false 404');
 verify(Stream::open($root,['escape'=>$relative.'/extra/private.mp3'],'escape','HEAD',null)['status']===403,'Legacy alias cannot bypass maximum depth');
 verify(Stream::scan($root,1)['truncated']===true,'Small scan budget enforced');
 echo "PASS deep media: 32 levels, shared editor/stream keys, Range, depth/budget warnings and alias confinement\n";
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $p){if($p->isDir()&&!$p->isLink())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($root);
}
