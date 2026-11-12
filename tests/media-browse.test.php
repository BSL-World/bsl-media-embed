<?php
declare(strict_types=1);
namespace Joomla\CMS\MVC\Controller {class BaseController{protected $app;protected $input;public function __construct($app,$input){$this->app=$app;$this->input=$input;}}}
namespace Joomla\CMS\Language {class Text{public static function _($s){return $s;}}}
namespace Joomla\CMS\Component {class ComponentHelper{public static string $root;public static function getParams($name){return new class{public function get($k,$default=''){return $k==='root'?ComponentHelper::$root:$default;}};}}}
namespace {
define('_JEXEC',1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
require __DIR__.'/../com_bslmedia/admin/src/Controller/ManageController.php';
use BSL\Component\Bslmedia\Administrator\Controller\ManageController;
class BrowseClosed extends Exception{}
$dir=__DIR__.'/../dist/browse-unit-'.getmypid();mkdir($dir,0700,true);mkdir($dir.'/site');mkdir($dir.'/media');mkdir($dir.'/media/Section');mkdir($dir.'/media/Section/Subsection');
define('JPATH_ROOT',realpath($dir.'/site'));\Joomla\CMS\Component\ComponentHelper::$root=realpath($dir.'/media');
$count=0;
function same($a,$b):void{global $count;if($a!==$b)throw new RuntimeException('Browse mismatch: '.var_export([$a,$b],true));$count++;}
function browse(array $rights,array $query):array{
 $user=new class($rights){public function __construct(private $r){}public function authorise($a,$asset){return in_array($a,$this->r,true);}};
 $app=new class($user){public function __construct(private $u){}public function getIdentity(){return $this->u;}public function close(){throw new BrowseClosed();}};
 $input=new class($query){public function __construct(private $q){}public function getCmd($k,$d){return $this->q[$k]??$d;}public function getString($k,$d){return $this->q[$k]??$d;}};
 ob_start();try{(new ManageController($app,$input))->browse();}catch(BrowseClosed){}catch(Throwable $e){ob_end_clean();throw $e;}
 return json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
}
try{
 $r=browse(['media.upload'],['mode'=>'destination','scope'=>'site']);same($r['folders'],[['relative'=>'Section','name'=>'Section']]);same($r['canCreate'],false);
 same(str_contains($r['path'],realpath($dir)),false);
 $r=browse(['media.create'],['mode'=>'destination','relative'=>'Section']);same($r['canCreate'],true);
 $r=browse(['core.admin'],['mode'=>'destination','relative'=>'Section/Subsection']);same($r['canCreate'],true);same($r['folders'],[]);
 foreach(['../site','Section/Subsection/Third'] as $path){$r=browse(['media.upload'],['mode'=>'destination','relative'=>$path]);same(isset($r['error']),true);}
 try{browse(['media.upload'],['mode'=>'root']);throw new RuntimeException('Uploader got root picker');}catch(RuntimeException $e){same($e->getCode(),403);}
 $r=browse(['core.admin'],['mode'=>'root','scope'=>'site']);same(count($r['folders']),2);
 echo "PASS $count bounded folder picker checks: Publisher confinement, root ACL, deeper creation and relative paths\n";
}finally{rmdir($dir.'/media/Section/Subsection');rmdir($dir.'/media/Section');rmdir($dir.'/media');rmdir($dir.'/site');rmdir($dir);}
}
