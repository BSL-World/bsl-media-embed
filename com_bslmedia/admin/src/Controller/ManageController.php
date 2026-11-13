<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Administrator\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary;
use BSL\Component\Bslmedia\Administrator\Service\RootBrowser;
use BSL\Component\Bslmedia\Administrator\Service\UploadLimits;
use BSL\Component\Bslmedia\Site\Service\MediaStream;
final class ManageController extends BaseController
{
    private function allowed(string $action):bool
    {
        $user=$this->app->getIdentity();return $user->authorise('core.admin','com_bslmedia')||$user->authorise($action,'com_bslmedia');
    }
    private function guard(string $action):void
    {
        if(!$this->allowed($action))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        if($this->input->getMethod()!=='POST'||!Session::checkToken('post'))throw new \RuntimeException(Text::_('COM_BSLMEDIA_REQUEST_REJECTED'),403);
    }
    private function root():string{return MediaStream::resolveRoot((string)ComponentHelper::getParams('com_bslmedia')->get('root',''),JPATH_ROOT);}
    private function done(string $folder=''):void{$this->setRedirect('index.php?option=com_bslmedia&folder='.rawurlencode($folder));}
    public function save():void
    {
        $this->guard('core.admin');
        try{
            $root=RootBrowser::saveValue(JPATH_ROOT,$this->input->post->getString('root_choice',''),
                $this->input->post->getInt('root_selected',0)===1,(string)ComponentHelper::getParams('com_bslmedia')->get('root',''));
            $limit=$this->input->post->getString('max_upload_value','');$unit=$this->input->post->getString('max_upload_unit','MB');
            $bytes=MediaLibrary::parseLimit($limit,$unit);
            $db=Factory::getContainer()->get(DatabaseInterface::class);
            $query=$db->getQuery(true)->select($db->quoteName('params'))->from($db->quoteName('#__extensions'))->where($db->quoteName('element').'='.$db->quote('com_bslmedia'))->where($db->quoteName('type').'='.$db->quote('component'));
            $params=new \Joomla\Registry\Registry((string)$db->setQuery($query)->loadResult());
            $params->set('root',$root);$params->set('max_upload_value',$limit);$params->set('max_upload_unit',$unit);$params->set('max_upload_bytes',$bytes);
            $query=$db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('params').'='.$db->quote((string)$params))->where($db->quoteName('element').'='.$db->quote('com_bslmedia'))->where($db->quoteName('type').'='.$db->quote('component'));
            $db->setQuery($query)->execute();$this->app->enqueueMessage(Text::_('COM_BSLMEDIA_SAVED'));
        }catch(\RuntimeException $e){$this->app->enqueueMessage(Text::_($e->getMessage()),'error');}
        $this->done();
    }
    public function create():void
    {
        $this->guard('media.create');$folder=$this->input->post->getString('folder','');
        $ajax=$this->input->post->getInt('ajax',0)===1;
        try{
            $name=$this->input->post->getString('new_folder','');MediaLibrary::createFolder($this->root(),$folder,$name);
            if($ajax){$this->json(['relative'=>($folder===''?'':$folder.'/').$name]);return;}
            $folder=($folder===''?'':$folder.'/').$name;
            $this->app->enqueueMessage(Text::_('COM_BSLMEDIA_CREATED'));
        }catch(\RuntimeException $e){
            if($ajax){$this->json(['error'=>Text::_($e->getMessage())],400);return;}
            $this->app->enqueueMessage(Text::_($e->getMessage()),'error');
        }
        $this->done($folder);
    }
    public function upload():void
    {
        if(isset($_SERVER['CONTENT_LENGTH'])&&UploadLimits::discarded($_SERVER,$_POST??[],$_FILES??[])){
            if(!$this->allowed('media.upload'))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
            $this->app->enqueueMessage(Text::sprintf('COM_BSLMEDIA_UPLOAD_POST_REJECTED',UploadLimits::format(UploadLimits::quantity((string)ini_get('post_max_size'))??0)),'error');
            $this->done();return;
        }
        $this->guard('media.upload');$folder=$this->input->post->getString('folder','');
        $files=$_FILES['media']??[];$names=$files['name']??[];
        if(!is_array($names))$names=[];
        $limit=MediaLibrary::limit(ComponentHelper::getParams('com_bslmedia')->toArray())['bytes'];
        $policy=UploadLimits::runtime($limit);
        if(!$policy['valid']||!$policy['enabled']||$policy['maxFiles']===0){
            $this->app->enqueueMessage(Text::_(!$policy['valid']?'COM_BSLMEDIA_UPLOAD_CONFIG_ERROR':'COM_BSLMEDIA_UPLOAD_DISABLED'),'error');$this->done($folder);return;
        }
        if(UploadLimits::exceedsPost($_SERVER,$policy['post'])){
            $this->app->enqueueMessage(Text::sprintf('COM_BSLMEDIA_UPLOAD_POST_REJECTED',UploadLimits::format($policy['post'])),'error');$this->done($folder);return;
        }
        $selected=$this->input->post->getInt('selected_count',0);
        if(max(count($names),$selected)>$policy['maxFiles']){
            $this->app->enqueueMessage(Text::sprintf('COM_BSLMEDIA_UPLOAD_COUNT_LIMIT',max(count($names),$selected),$policy['maxFiles']),'error');$this->done($folder);return;
        }
        $trusted=[];
        foreach($names as $i=>$name){
            $tmp=$files['tmp_name'][$i]??null;
            if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK&&is_string($tmp)&&is_uploaded_file($tmp))$trusted[]=$tmp;
        }
        $requestIssue=UploadLimits::aggregateIssue($trusted,$policy);
        if($requestIssue!==null){
            $this->app->enqueueMessage(Text::sprintf('COM_BSLMEDIA_UPLOAD_REQUEST_REJECTED',UploadLimits::exact($requestIssue['actual']),UploadLimits::exact($requestIssue['limit'])),'error');$this->done($folder);return;
        }
        $messages=[];$errors=0;
        foreach($names as $i=>$name){
            if(!is_string($name))continue;
            try{
                $error=$files['error'][$i]??UPLOAD_ERR_NO_FILE;
                if($error===UPLOAD_ERR_INI_SIZE){
                    $errors++;$messages[]=Text::sprintf('COM_BSLMEDIA_UPLOAD_PHP_REJECTED',htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),UploadLimits::format($policy['upload']));continue;
                }
                if($error!==UPLOAD_ERR_OK)throw new \RuntimeException(in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'COM_BSLMEDIA_TOO_LARGE':'COM_BSLMEDIA_UPLOAD_ERROR');
                // Reject folder uploads: only plain selected files are accepted.
                if(isset($files['full_path'][$i])&&$files['full_path'][$i]!==$name)throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
                $tmp=$files['tmp_name'][$i]??'';
                if(!is_string($tmp)||!is_uploaded_file($tmp))throw new \RuntimeException('COM_BSLMEDIA_UPLOAD_ERROR');
                $issue=UploadLimits::fileIssue($tmp,$name,$policy);
                if($issue!==null){
                    $errors++;
                    $messages[]=in_array($issue['reason'],['component','upload'],true)
                        ?Text::sprintf('COM_BSLMEDIA_UPLOAD_FILE_LIMIT',htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),UploadLimits::exact($issue['actual']),UploadLimits::exact($issue['limit']),Text::_($issue['reason']==='component'?'COM_BSLMEDIA_LIMIT_COMPONENT':'COM_BSLMEDIA_LIMIT_UPLOAD'))
                        :Text::_('COM_BSLMEDIA_UPLOAD_ERROR');
                    continue;
                }
                MediaLibrary::upload($this->root(),$folder,$tmp,$name,$limit);
                $messages[]=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').': '.Text::_('COM_BSLMEDIA_UPLOADED');
            }catch(\RuntimeException $e){$errors++;$messages[]=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').': '.Text::_($e->getMessage());}
        }
        if($selected>count($names)){$errors++;$messages[]=Text::_('COM_BSLMEDIA_TRUNCATED');}
        if($messages===[]){$errors++;$messages[] = Text::_('COM_BSLMEDIA_UPLOAD_ERROR');}
        // A single result list retains every per-file outcome; files are handled sequentially.
        $this->app->enqueueMessage(implode('<br>',$messages),$errors?'warning':'message');$this->done($folder);
    }
    public function browse():void
    {
        $destination=$this->input->getCmd('mode','root')==='destination';
        if($destination){
            if(!$this->allowed('core.manage')&&!$this->allowed('media.upload')&&!$this->allowed('media.create'))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        }elseif(!$this->allowed('core.admin'))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        try{
            if(!$destination){
                $this->json(RootBrowser::read(JPATH_ROOT,$this->root(),$this->input->getString('relative',''),$this->input->getCmd('initial','')));
                return;
            }
            $anchor=$this->root();
            // Never turn the picker into a whole-drive or filesystem-root explorer.
            if($anchor===''||dirname($anchor)===$anchor)throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
            $depth=MediaStream::MAX_DEPTH;
            $relative=$this->input->getString('relative','');$dir=MediaLibrary::directory($anchor,$relative,$depth);
            $folders=MediaLibrary::folders($anchor,$relative,$depth);
            $result=['path'=>$destination?(Text::_('COM_BSLMEDIA_ROOT_FOLDER').($relative===''?'':' / '.$relative)):$dir,'relative'=>$relative,'canCreate'=>$destination&&$this->allowed('media.create')&&($relative===''||count(explode('/',$relative))<MediaStream::MAX_DEPTH),'folders'=>array_map(static fn($r)=>['relative'=>$r,'name'=>basename($r)],$folders)];
            $this->json($result);
        }catch(\RuntimeException $e){$this->json(['error'=>Text::_($e->getMessage())],400);}
    }
    private function json(array $result,int $status=200):void
    {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);$this->app->close();
    }
}
