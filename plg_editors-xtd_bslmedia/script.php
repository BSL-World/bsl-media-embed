<?php
declare(strict_types=1);
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class PlgEditorsXtdBslmediaInstallerScript
{
    // Runs only for a new button plugin. Updates keep the administrator's state.
    public function install($parent): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('editors-xtd'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('bslmedia'));
        $db->setQuery($query)->execute();
        return true;
    }
}
