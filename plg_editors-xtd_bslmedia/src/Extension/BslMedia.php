<?php
declare(strict_types=1);
namespace BSL\Plugin\EditorsXtd\BslMedia\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

final class BslMedia extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'onEditorButtonsSetup'];
    }
    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        if (!in_array($this->_name, $event->getDisabledButtons(), true)) {
            $event->getButtonsRegistry()->add($this->onDisplay($event->getEditorId()));
        }
    }
    public function onDisplay($name): Button
    {
        $wa = $this->getApplication()->getDocument()->getWebAssetManager();
        if (!$wa->assetExists('script', 'editor-button.bslmedia')) {
            // A full same-site URL bypasses WAM's filesystem lookup of /subdirectory/media/.
            $wa->registerScript('editor-button.bslmedia', Uri::root() . 'media/plg_editors-xtd_bslmedia/button.js',
                ['version' => '0.8.1'], ['type' => 'module'], ['editors']);
        }
        // JCE's toolbar menu and Joomla's button layout use different asset names.
        $wa->useScript('editor-button.bslmedia');
        $app=$this->getApplication();
        $app->getDocument()->addScriptOptions('bslmedia.library', [
            'url'=>Uri::root().($app->isClient('administrator')?'administrator/':'')
                .'index.php?option=com_bslmedia&task=library.files',
        ]);
        foreach (['INPUT', 'CANCEL', 'INSERT', 'INVALID', 'REMOVED', 'INSERT_ERROR',
            'FILES','LINKS','SEARCH','FOLDER','TYPE','ALL','ROOT','LOADING','LOAD_ERROR','EMPTY','PREVIOUS','NEXT','LIMIT',
            'FORMAT','ALIGN','ALIGN_NONE','ALIGN_CENTER','ALIGN_LEFT','ALIGN_RIGHT','WIDTH','GAP','AUTO','MOBILE_HELP','LAYOUT_INVALID'] as $key) {
            Text::script('PLG_EDITORSXTD_BSLMEDIA_' . $key);
        }
        return new Button('bslmedia', [
            'action' => 'bsl-media', 'text' => 'BSL Media', 'icon' => 'play', 'name' => 'editors-xtd_bslmedia',
        ], ['editor' => (string) $name]);
    }
}
