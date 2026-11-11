<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;
use BSL\Component\Bslmedia\Site\Service\MediaStream;
final class MediaController extends BaseController
{
    public function stream(): void
    {
        $params=ComponentHelper::getParams('com_bslmedia');
        $map=json_decode((string)$params->get('files','{}'),true);
        $method=$_SERVER['REQUEST_METHOD'] ?? '';
        $root=MediaStream::resolveRoot((string)$params->get('root',''),JPATH_ROOT);
        $r=MediaStream::open($root,is_array($map)?$map:[],
            $this->input->getString('file',''),$method,$_SERVER['HTTP_RANGE'] ?? null);
        if ($this->input->getInt('download',0) === 1 && isset($r['headers']['Content-Disposition'])) {
            $r['headers']['Content-Disposition']=str_replace('inline;','attachment;',$r['headers']['Content-Disposition']);
        }
        // No template/debug/compression output may be appended to media bytes.
        while (ob_get_level()>0) ob_end_clean();
        ini_set('zlib.output_compression','0');
        http_response_code($r['status']);
        foreach ($r['headers'] as $name=>$value) header($name.': '.$value,true);
        if (!isset($r['headers']['Content-Length'])) header('Content-Length: 0');
        MediaStream::emitBody($r);
        $this->app->close();
    }
}
