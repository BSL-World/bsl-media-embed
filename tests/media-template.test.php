<?php
declare(strict_types=1);
namespace Joomla\CMS\Language {class Text{public static function _($s){return $s;}}}
namespace Joomla\CMS\HTML {class HTMLHelper{public static function _($s,...$args){return $s==='date'?$args[0]:'<input type="hidden" name="csrf" value="1">';}}}
namespace {
define('_JEXEC',1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
$params=new class{public function get($key,$default=null){return $key==='root'?'../media':$default;}};
$root='';$folder='';$count=0;
foreach([true,false] as $admin){
 $allowed=fn($action)=>$admin||$action==='media.upload';
 ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$html=ob_get_clean();
 foreach(['id="bsl-root"','id="bsl-folder-picker"','manage.save'] as $needle){if(str_contains($html,$needle)!==$admin)throw new RuntimeException('Settings ACL rendering');$count++;}
 if(str_contains($html,'../media')!==$admin)throw new RuntimeException('Private path visible to unconfigured uploader');
}
// Render a configured uploader view with nested media; no server/site bootstrap.
$root=__DIR__.'/../dist/template-unit-'.getmypid();mkdir($root,0700,true);mkdir($root.'/Album');file_put_contents($root.'/Album/song.opus','fixture');
file_put_contents($root.'/sample.mp3','fixture');file_put_contents($root.'/sample.mp4','fixture');
try{
 $allowed=fn($action)=>in_array($action,['media.upload','media.create'],true);
 ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$html=ob_get_clean();
 foreach(['manage.create','manage.upload','multiple','data-copy=','{bslaudio file=','Album','COM_BSLMEDIA_NO_DESTRUCTIVE'] as $needle){if(!str_contains($html,$needle))throw new RuntimeException('Missing UI: '.$needle);$count++;}
 if(str_contains($html,'id="bsl-root"'))throw new RuntimeException('Uploader can configure root');
 if(substr_count($html,'class="btn btn-secondary bsl-copy"')!==3)throw new RuntimeException('File must have exactly one row/copy button');$count++;
 if(str_contains($html,'<select name="folder"'))throw new RuntimeException('Destination dropdown must be removed');$count++;
 if(!preg_match('~<dialog\b.*id="bsl-create-form".*</dialog>~s',$html))throw new RuntimeException('Folder creation must be inside picker');$count++;
 if(!preg_match('~<form id="bsl-upload-form"(.*?)</form>~s',$html,$form))throw new RuntimeException('Missing upload form');
 if(preg_match('~<button[^>]+type="submit"~',$form[1]))throw new RuntimeException('Extra upload submit button');$count++;
 if(!str_contains($html,'COM_BSLMEDIA_DEPTH_HELP'))throw new RuntimeException('Missing explicit depth explanation');$count++;
 foreach(['id="bsl-folder-filter"','data-sort="name"','data-sort="size"','data-sort="modified"','data-folder="Album"','data-modified="','COM_BSLMEDIA_DESTINATION_HELP','COM_BSLMEDIA_DATE_HELP'] as $needle){if(!str_contains($html,$needle))throw new RuntimeException('Missing list feature: '.$needle);$count++;}
 if(str_contains($html,'COM_BSLMEDIA_AMBIGUOUS'))throw new RuntimeException('Redundant stem warning beside canonical shortcode');$count++;
 echo "PASS $count template checks: inline settings ACL, forms, single media row, copying and restrictions\n";
}finally{unlink($root.'/Album/song.opus');unlink($root.'/sample.mp3');unlink($root.'/sample.mp4');rmdir($root.'/Album');rmdir($root);}
}
