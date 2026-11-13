<?php
declare(strict_types=1);
namespace Joomla\CMS\Language {class Text{public static function _($s){return $s;}public static function sprintf($s,...$args){return vsprintf($s,$args);}}}
namespace Joomla\CMS\HTML {class HTMLHelper{public static function _($s,...$args){return $s==='date'?$args[0]:'<input type="hidden" name="csrf" value="1">';}}}
namespace {
define('_JEXEC',1);
define('JPATH_ROOT',__DIR__);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/RootBrowser.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/UploadLimits.php';
$params=new class{public function get($key,$default=null){return $key==='root'?'../media':$default;}};
$root='';$folder='';$count=0;
foreach([true,false] as $admin){
 $allowed=fn($action)=>$admin||$action==='media.upload';
 ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$html=ob_get_clean();
 foreach(['id="bsl-root-choice"','id="bsl-root-location"','id="bsl-folder-picker"','manage.save'] as $needle){if(str_contains($html,$needle)!==$admin)throw new RuntimeException('Settings ACL rendering');$count++;}
 if(str_contains($html,'../media')||str_contains($html,'name="root"'))throw new RuntimeException('Manual root path must not appear');
}
// Render a configured uploader view with nested media; no server/site bootstrap.
$root=__DIR__.'/../dist/template-unit-'.getmypid();mkdir($root,0700,true);mkdir($root.'/Album');file_put_contents($root.'/Album/song.opus','fixture');
file_put_contents($root.'/sample.mp3','fixture');file_put_contents($root.'/sample.mp4','fixture');
try{
 $allowed=fn($action)=>in_array($action,['media.upload','media.create'],true);
 ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$html=ob_get_clean();
 foreach(['manage.create','manage.upload','multiple','data-copy=','{bslaudio file=','Album','COM_BSLMEDIA_NO_DESTRUCTIVE'] as $needle){if(!str_contains($html,$needle))throw new RuntimeException('Missing UI: '.$needle);$count++;}
 if(str_contains($html,'id="bsl-root"'))throw new RuntimeException('Uploader can configure root');
 if(substr_count($html,'class="btn btn-secondary bsl-copy"')!==2)throw new RuntimeException('Only current-folder files should be shown once');$count++;
 if(str_contains($html,'<select name="folder"'))throw new RuntimeException('Destination dropdown must be removed');$count++;
 if(str_contains($html,'<dialog')||!str_contains($html,'id="bsl-create-panel"'))throw new RuntimeException('Uploader creates in the page without a folder dialog');$count++;
 if(!preg_match('~<form id="bsl-upload-form"(.*?)</form>~s',$html,$form))throw new RuntimeException('Missing upload form');
 if(preg_match('~<button[^>]+type="submit"~',$form[1]))throw new RuntimeException('Extra upload submit button');$count++;
 foreach(['id="bsl-destination-browse"','id="bsl-folder-filter"','COM_BSLMEDIA_DESTINATION','COM_BSLMEDIA_DEPTH_HELP'] as $needle){if(str_contains($html,$needle))throw new RuntimeException('Old duplicate destination UI: '.$needle);$count++;}
 foreach(['id="bsl-file-search"','id="bsl-current-path"','data-sort="name"','data-sort="size"','data-sort="modified"','data-modified="','COM_BSLMEDIA_UPLOAD_HERE','COM_BSLMEDIA_DATE_HELP'] as $needle){if(!str_contains($html,$needle))throw new RuntimeException('Missing list feature: '.$needle);$count++;}
 if(str_contains($html,'COM_BSLMEDIA_AMBIGUOUS'))throw new RuntimeException('Redundant stem warning beside canonical shortcode');$count++;
 $folder='Album';ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$nested=ob_get_clean();
 if(!str_contains($nested,'song.opus')||str_contains($nested,'sample.mp3')||str_contains($nested,'sample.mp4'))throw new RuntimeException('Folder navigation must show exactly its own files');$count++;
 preg_match_all('~name="folder" value="([^"]*)"~',$nested,$targets);
 if($targets[1]!==['Album','Album'])throw new RuntimeException('Create/upload targets must both equal the open folder');$count++;
 if(!str_contains($nested,'folder='))throw new RuntimeException('Missing breadcrumb navigation');$count++;
 $allowed=fn($action)=>true;ob_start();require __DIR__.'/../com_bslmedia/admin/tmpl/library.php';$adminHtml=ob_get_clean();
 if(substr_count($adminHtml,'<dialog')!==1||!str_contains($adminHtml,'id="bsl-settings"'))throw new RuntimeException('Only admin root selection has a dialog');$count++;
 if(str_contains($adminHtml,realpath($root))||str_contains($adminHtml,'name="root"')||str_contains($adminHtml,'id="bsl-root"'))throw new RuntimeException('No absolute/manual root field is permitted');$count++;
 if(!str_contains($adminHtml,'COM_BSLMEDIA_BESIDE_SITE')||!str_contains($adminHtml,'name="root_selected" value="1"'))throw new RuntimeException('Existing root needs human location and valid chooser state');$count++;
 echo "PASS $count template checks: unified folder view, identical upload/create targets, settings ACL and no duplicate pickers\n";
}finally{unlink($root.'/Album/song.opus');unlink($root.'/sample.mp3');unlink($root.'/sample.mp4');rmdir($root.'/Album');rmdir($root);}
}
