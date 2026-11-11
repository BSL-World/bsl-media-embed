<?php

/**
 * @package    BSL.MediaEmbed
 * @subpackage plg_content_bslmediaembed
 *
 * @copyright  Copyright (C) 2026 Vasilyev Alexander (BSL-World.ru).
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use BSL\Plugin\Content\BslMediaEmbed\Extension\BslMediaEmbed;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\DispatcherInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(
                BslMediaEmbed::class,
                static function (Container $container): PluginInterface {
                    $config = (array) PluginHelper::getPlugin('content', 'bslmediaembed');
                    // Early Joomla 5 requires the dispatcher constructor; newer 5/6 accepts config.
                    $type = (new ReflectionMethod(CMSPlugin::class, '__construct'))->getParameters()[0]->getType();
                    $plugin = $type instanceof ReflectionNamedType && $type->getName() === DispatcherInterface::class
                        ? new BslMediaEmbed($container->get(DispatcherInterface::class), $config)
                        : new BslMediaEmbed($config);

                    $plugin->setApplication(Factory::getApplication());

                    return $plugin;
                }
            )
        );
    }
};
