<?php
declare(strict_types=1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/site/src/Service/EditorLibrary.php';
use BSL\Component\Bslmedia\Site\Service\EditorLibrary;
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$user=new class {public bool $guest=true;public array $rights=[];public function authorise($a,$c){return in_array($c.'.'.$a,$this->rights,true);}};
$user->rights=['com_content.core.edit'];check(!EditorLibrary::canRead($user),'Guest must be rejected even with rights');
$user->guest=false;$user->rights=[];check(!EditorLibrary::canRead($user),'Registered visitor rejected');
foreach(['com_content.core.create','com_content.core.edit','com_content.core.edit.own','com_bslmedia.media.upload'] as $right){$user->rights=[$right];check(EditorLibrary::canRead($user),'Editor permission '.$right);}
$root=__DIR__.'/../dist/editor-library-fixture-'.getmypid();mkdir($root);mkdir($root.'/Section');
try {
 for($i=0;$i<65;$i++)file_put_contents($root.'/clip'.$i.'.mp3','test');
 file_put_contents($root.'/Section/Урок.opus','test');file_put_contents($root.'/clip.mp4','test');file_put_contents($root.'/private.txt','test');
 $page=EditorLibrary::page($root);check($page['total']===67 && count($page['items'])===30 && $page['pages']===3,'Bounded deduplicated catalogue');
 $last=EditorLibrary::page($root,'','*','',999);check($last['page']===3 && count($last['items'])===7,'Page clamping');
 $filtered=EditorLibrary::page($root,'урок','Section','opus');check($filtered['total']===1,'Unicode search and filters');
 check((bool)preg_match('/^\{bslaudio file="media-[a-f0-9]{32}"\}$/',$filtered['items'][0]['shortcode']),'Canonical Unicode key');
 $video=EditorLibrary::page($root,'','*','mp4');check(str_starts_with($video['items'][0]['shortcode'],'{bslvideo'),'Video shortcode');
 check(EditorLibrary::page($root,'','../../')['total']===0,'Folder filter cannot escape');
 echo "PASS editor ACL, guest denial, pagination, deduplication, Unicode search, filters and shortcode types\n";
} finally {foreach(glob($root.'/Section/*') as $p)unlink($p);rmdir($root.'/Section');foreach(glob($root.'/*') as $p)unlink($p);rmdir($root);}
