<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use BSL\Component\Bslmedia\Site\Service\EditorLibrary;
use BSL\Component\Bslmedia\Site\Service\MediaStream;

class LibraryController extends BaseController
{
    public function files(): void
    {
        $data=['error'=>'Unavailable']; $status=403;
        if (EditorLibrary::canRead($this->app->getIdentity())) {
            $params=ComponentHelper::getParams('com_bslmedia');
            $root=MediaStream::resolveRoot((string)$params->get('root',''),JPATH_ROOT);
            if ($root==='') { $status=503; }
            else {
                $data=EditorLibrary::page($root,$this->input->getString('q',''),
                    $this->input->getString('folder','*'),$this->input->getString('type',''),$this->input->getInt('page',1));
                $status=200;
            }
        }
        while (ob_get_level()>0) ob_end_clean();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        $this->app->close();
    }
}
