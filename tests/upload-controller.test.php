<?php
declare(strict_types=1);
namespace Joomla\CMS\MVC\Controller {class BaseController{protected $app;protected $input;public string $redirect='';public function __construct($a,$i){$this->app=$a;$this->input=$i;}public function setRedirect($url){$this->redirect=$url;}}}
namespace Joomla\CMS\Session {class Session{public static bool $valid=true;public static function checkToken($method){return self::$valid;}}}
namespace Joomla\CMS\Language {class Text{public static function _($s){return $s;}public static function sprintf($s,...$args){return $s.'|'.implode('|',$args);}}}
namespace Joomla\CMS\Component {class ComponentHelper{public static array $params=[];public static function getParams($name){return new class{public function get($key,$default=''){return ComponentHelper::$params[$key]??$default;}public function toArray(){return ComponentHelper::$params;}};}}}
namespace Joomla\CMS\Toolbar {class ToolbarHelper{public static function title($name){}public static function preferences($name){}}}
namespace Joomla\CMS\Uri {class Uri{public static function root(){return 'https://site.test/';}}}
namespace Joomla\CMS\HTML {class HTMLHelper{public static function _($key,...$args){return '<input type="hidden" name="csrf" value="1">';}}}
namespace BSL\Component\Bslmedia\Administrator\Controller {function is_uploaded_file($path){return $path===($GLOBALS['trustedUploadFixture']??null);}}
namespace {
define('_JEXEC',1);
require __DIR__.'/../com_bslmedia/site/src/Service/MediaStream.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/MediaLibrary.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/RootBrowser.php';
require __DIR__.'/../com_bslmedia/admin/src/Service/UploadLimits.php';
require __DIR__.'/../com_bslmedia/admin/src/Controller/ManageController.php';
require __DIR__.'/../com_bslmedia/admin/src/Controller/DisplayController.php';
use BSL\Component\Bslmedia\Administrator\Controller\ManageController;
$dir=__DIR__.'/../dist/upload-request-'.getmypid();mkdir($dir);define('JPATH_ROOT',realpath($dir));
$tmp=$dir.'/source.bin';file_put_contents($tmp,str_repeat('x',200));$GLOBALS['trustedUploadFixture']=$tmp;
\Joomla\CMS\Component\ComponentHelper::$params=['root'=>realpath($dir),'max_upload_value'=>'0.0001','max_upload_unit'=>'MB'];
$rights=true;
$user=new class($rights){public function __construct(public bool $allow){}public function authorise($action,$asset){return $this->allow&&$action==='media.upload';}};
$app=new class($user){public array $messages=[];public function __construct(private $user){}public function getIdentity(){return $this->user;}public function enqueueMessage($message,$type='message'){$this->messages[]=$message;}public function getDocument(){return $this;}public function getWebAssetManager(){return $this;}public function registerAndUseScript(...$args){}};
$input=new class{public object $post;public function __construct(){$this->post=$this;}public function getMethod(){return 'POST';}public function getString($key,$default=''){return $_POST[$key]??$default;}public function getInt($key,$default=0){return (int)($_POST[$key]??$default);}};
function hasMessage($app,$key){foreach($app->messages as $m)if(str_contains($m,$key))return;throw new RuntimeException('Missing message '.$key);}
try{
 // A forged client limit and forged FILES size cannot bypass actual disk-size enforcement.
 $_SERVER=['REQUEST_METHOD'=>'POST','CONTENT_LENGTH'=>'1000'];$_POST=['csrf'=>'1','folder'=>'','selected_count'=>1,'limit'=>999999999];
 $_FILES=['media'=>['name'=>['large.mp3'],'tmp_name'=>[$tmp],'error'=>[UPLOAD_ERR_OK],'size'=>[1]]];
 $controller=new ManageController($app,$input);$controller->upload();hasMessage($app,'COM_BSLMEDIA_UPLOAD_FILE_LIMIT');
 if(file_exists($dir.'/large.mp3'))throw new RuntimeException('Oversized bypass was written');
 // Empty oversized POST is a read-only diagnostic, even though the token was discarded.
 $app->messages=[];$_POST=[];$_FILES=[];$_SERVER['CONTENT_LENGTH']='213578537';\Joomla\CMS\Session\Session::$valid=false;
 $controller->upload();hasMessage($app,'COM_BSLMEDIA_UPLOAD_POST_REJECTED');
 if($controller->redirect==='')throw new RuntimeException('No recovery redirect');
 $user->allow=false;try{$controller->upload();throw new RuntimeException('Guest/unauthorized overflow exposed');}catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}$user->allow=true;
 // The legacy form without a GET task reaches display; it reports the same error.
 $app->messages=[];ob_start();(new \BSL\Component\Bslmedia\Administrator\Controller\DisplayController($app,$input))->display();ob_end_clean();hasMessage($app,'COM_BSLMEDIA_UPLOAD_POST_REJECTED');
 // Normal-sized requests still require CSRF, regardless of size/provenance guards.
 $_SERVER['CONTENT_LENGTH']='1000';$_POST=['folder'=>''];
 try{$controller->upload();throw new RuntimeException('CSRF bypass');}catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}
 if(\BSL\Component\Bslmedia\Administrator\Service\UploadLimits::quantity((string)ini_get('post_max_size'))<80000){
  \Joomla\CMS\Session\Session::$valid=true;$app->messages=[];
  \Joomla\CMS\Component\ComponentHelper::$params['max_upload_value']='0.001';
  $_POST=['csrf'=>'1','selected_count'=>2];$_SERVER['CONTENT_LENGTH']='1000';
  $_FILES=['media'=>['name'=>['one.mp3','two.mp3'],'tmp_name'=>[$tmp,$tmp],'error'=>[0,0],'size'=>[1,1]]];
  $controller->upload();hasMessage($app,'COM_BSLMEDIA_UPLOAD_REQUEST_REJECTED');
 }
 echo "PASS upload controller: client bypass/actual size, discarded POST recovery, permission and CSRF guards, legacy display fallback\n";
}finally{unlink($tmp);rmdir($dir);}
}
