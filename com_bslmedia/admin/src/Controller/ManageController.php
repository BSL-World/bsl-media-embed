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
            $root=$this->input->post->getString('root','');$limit=$this->input->post->getString('max_upload_value','');$unit=$this->input->post->getString('max_upload_unit','MB');
            $bytes=MediaLibrary::parseLimit($limit,$unit);
            if($root!==''&&MediaStream::resolveRoot($root,JPATH_ROOT)==='')throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
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
        $this->guard('media.upload');$folder=$this->input->post->getString('folder','');
        $files=$_FILES['media']??[];$names=$files['name']??[];
        if(!is_array($names))$names=[];
        $limit=MediaLibrary::limit(ComponentHelper::getParams('com_bslmedia')->toArray())['bytes'];
        $messages=[];$errors=0;
        foreach($names as $i=>$name){
            if(!is_string($name))continue;
            try{
                $error=$files['error'][$i]??UPLOAD_ERR_NO_FILE;
                if($error!==UPLOAD_ERR_OK)throw new \RuntimeException(in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'COM_BSLMEDIA_TOO_LARGE':'COM_BSLMEDIA_UPLOAD_ERROR');
                // Reject folder uploads: only plain selected files are accepted.
                if(isset($files['full_path'][$i])&&$files['full_path'][$i]!==$name)throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
                $tmp=$files['tmp_name'][$i]??'';
                if(!is_string($tmp)||!is_uploaded_file($tmp))throw new \RuntimeException('COM_BSLMEDIA_UPLOAD_ERROR');
                MediaLibrary::upload($this->root(),$folder,$tmp,$name,$limit);
                $messages[]=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').': '.Text::_('COM_BSLMEDIA_UPLOADED');
            }catch(\RuntimeException $e){$errors++;$messages[]=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').': '.Text::_($e->getMessage());}
        }
        $selected=$this->input->post->getInt('selected_count',0);
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
