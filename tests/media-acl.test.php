<?php
declare(strict_types=1);
namespace Joomla\CMS\MVC\Controller {class BaseController{protected $app;protected $input;public function __construct($a,$i){$this->app=$a;$this->input=$i;}}}
namespace Joomla\CMS\Session {class Session{public static bool $valid=false;public static function checkToken($method){return self::$valid;}}}
namespace Joomla\CMS\Language {class Text{public static function _($s){return $s;}}}
namespace {
define('_JEXEC',1);require __DIR__.'/../com_bslmedia/admin/src/Controller/ManageController.php';
use BSL\Component\Bslmedia\Administrator\Controller\ManageController;
$count=0;
foreach(['save'=>'core.admin','create'=>'media.create','upload'=>'media.upload','browse'=>'core.admin'] as $method=>$permission){
 foreach([[],[$permission]] as $rights){
 $user=new class($rights){public function __construct(private array $r){}public function authorise($action,$asset){return in_array($action,$this->r,true);}};
 $app=new class($user){public function __construct(private $u){}public function getIdentity(){return $this->u;}};
 $input=new class{public function getMethod(){return 'POST';}public function getCmd($key,$default){return $default;}};
 if($method==='browse'&&$rights!==[])continue;
 $c=new ManageController($app,$input);
 try{$c->$method();throw new RuntimeException('Mutation allowed without ACL/CSRF');}
 catch(RuntimeException $e){if($e->getCode()!==403)throw $e;$count++;}
 }
}
echo "PASS $count controller ACL/CSRF rejections before filesystem or DB operations\n";
// Even a valid CSRF token must not replace action-specific authorization.
\Joomla\CMS\Session\Session::$valid=true;
foreach(['save','create','upload','browse'] as $method){
 $user=new class{public function authorise($action,$asset){return false;}};
 $app=new class($user){public function __construct(private $u){}public function getIdentity(){return $this->u;}};
 $input=new class{public function getMethod(){return 'POST';}public function getCmd($key,$default){return $default;}};
 try{(new ManageController($app,$input))->$method();throw new RuntimeException('ACL bypass');}
 catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}
}
echo "PASS 4 action ACL rejections with valid CSRF token\n";
}
