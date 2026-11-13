<?php
namespace BSL\Component\Bslmedia\Administrator\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use BSL\Component\Bslmedia\Site\Service\MediaStream;
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary;
use BSL\Component\Bslmedia\Administrator\Service\UploadLimits;
final class DisplayController extends BaseController
{
    public function display($cachable=false,$urlparams=[])
    {
        $user=$this->app->getIdentity();
        $allowed=static fn($action)=>$user->authorise('core.admin','com_bslmedia')||$user->authorise($action,'com_bslmedia');
        if(!$allowed('core.manage')&&!$allowed('media.upload')&&!$allowed('media.create'))throw new \RuntimeException('Forbidden',403);
        ToolbarHelper::title('BSL Media');
        if($allowed('core.admin'))ToolbarHelper::preferences('com_bslmedia');
        $params=ComponentHelper::getParams('com_bslmedia');$root=MediaStream::resolveRoot((string)$params->get('root',''),JPATH_ROOT);
        if(isset($_SERVER['CONTENT_LENGTH'])&&UploadLimits::discarded($_SERVER,$_POST??[],$_FILES??[])){
            $postLimit=UploadLimits::quantity((string)ini_get('post_max_size'))??0;
            $this->app->enqueueMessage(\Joomla\CMS\Language\Text::sprintf('COM_BSLMEDIA_UPLOAD_POST_REJECTED',UploadLimits::format($postLimit)),'error');
        }
        $folder=$this->input->getString('folder','');
        try{if($root!=='')MediaLibrary::directory($root,$folder);}catch(\RuntimeException $e){$this->app->enqueueMessage(\Joomla\CMS\Language\Text::_($e->getMessage()),'error');$folder='';}
        $assets=$this->app->getDocument()->getWebAssetManager();
        $assets->registerAndUseScript('com_bslmedia.upload-limits',\Joomla\CMS\Uri\Uri::root().'media/com_bslmedia/upload-limits.js',['version'=>'0.8.4'],['defer'=>true]);
        $assets->registerAndUseScript('com_bslmedia.library',\Joomla\CMS\Uri\Uri::root().'media/com_bslmedia/library.js',['version'=>'0.8.4'],['defer'=>true],['com_bslmedia.upload-limits']);
        require dirname(__DIR__,2).'/tmpl/library.php';
        return $this;
    }
}
