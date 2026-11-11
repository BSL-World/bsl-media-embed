<?php
namespace BSL\Component\Bslmedia\Administrator\Dispatcher;
defined('_JEXEC') or die;
final class Dispatcher extends \Joomla\CMS\Dispatcher\ComponentDispatcher
{
    protected function checkAccess()
    {
        $user=$this->app->getIdentity();
        if ($this->input->getCmd('task')==='library.files'
            && \BSL\Component\Bslmedia\Site\Service\EditorLibrary::canRead($user)) return;
        foreach(['core.admin','core.manage','media.create','media.upload'] as $action)if($user->authorise($action,'com_bslmedia'))return;
        throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'),403);
    }
}
