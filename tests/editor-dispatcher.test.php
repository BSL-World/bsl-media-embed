<?php
namespace Joomla\CMS\Dispatcher {
 class ComponentDispatcher {protected $app;protected $input;public function __construct($app,$input){$this->app=$app;$this->input=$input;}public function check(){return $this->checkAccess();}}
}
namespace Joomla\CMS\Language {class Text {public static function _($s){return $s;}}}
namespace {
 define('_JEXEC',1);
 require __DIR__.'/../com_bslmedia/site/src/Service/EditorLibrary.php';
 require __DIR__.'/../com_bslmedia/admin/src/Dispatcher/Dispatcher.php';
 $user=new class {public bool $guest=false;public function authorise($a,$c){return $a==='core.edit'&&$c==='com_content';}};
 $app=new class($user){public function __construct(public $user){}public function getIdentity(){return $this->user;}};
 $input=new class{public string $task='library.files';public function getCmd($key){return $this->task;}};
 $dispatcher=new \BSL\Component\Bslmedia\Administrator\Dispatcher\Dispatcher($app,$input);
 $dispatcher->check();
 foreach(['display','manage.save','manage.upload','manage.create','manage.browse','library.other'] as $task){
  $input->task=$task;
  try{$dispatcher->check();throw new \RuntimeException('Author received management access');}
  catch(\RuntimeException $e){if($e->getCode()!==403)throw $e;}
 }
 $input->task='library.files';$user->guest=true;
 try{$dispatcher->check();throw new \RuntimeException('Guest catalogue access');}
 catch(\RuntimeException $e){if($e->getCode()!==403)throw $e;}
 echo "PASS author can only list media; component management and guests remain denied\n";
}
