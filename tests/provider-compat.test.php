<?php
declare(strict_types=1);
namespace Joomla\Event {
    interface DispatcherInterface {}
    interface SubscriberInterface { public static function getSubscribedEvents(): array; }
}
namespace Joomla\CMS\Extension { interface PluginInterface {} }
namespace Joomla\CMS\Plugin {
    trait ApplicationSetter {
        public object $application;
        public array $configuration;
        public function setApplication(object $app): void { $this->application = $app; }
    }
    if (($GLOBALS['argv'][1] ?? '') === 'old') {
        class CMSPlugin implements \Joomla\CMS\Extension\PluginInterface {
            use ApplicationSetter;
            public function __construct(\Joomla\Event\DispatcherInterface $dispatcher, array $config = []) {
                $this->configuration = $config;
            }
        }
    } else {
        class CMSPlugin implements \Joomla\CMS\Extension\PluginInterface {
            use ApplicationSetter;
            public function __construct($config = []) { $this->configuration = $config; }
        }
    }
    class PluginHelper {
        public static function getPlugin(string $group, string $name): object {
            return (object) ['name' => $name, 'type' => $group, 'params' => '{"keep":1}'];
        }
    }
}
namespace Joomla\CMS {
    class Factory { public static function getApplication(): object { return (object) ['test' => true]; } }
}
namespace Joomla\DI {
    interface ServiceProviderInterface { public function register(Container $container): void; }
    class Container {
        public object $plugin;
        public function set($id, $factory): void { $this->plugin = $factory(); }
        public function lazy($class, $callback): callable { return fn() => $callback($this); }
        public function get($id): object { return new class implements \Joomla\Event\DispatcherInterface {}; }
    }
}
namespace {
    define('_JEXEC', 1);
    $root = dirname(__DIR__);
    require $root . '/plg_content_bslmediaembed/src/Extension/BslMediaEmbed.php';
    require $root . '/plg_editors-xtd_bslmedia/src/Extension/BslMedia.php';
    foreach (['plg_content_bslmediaembed' => 'bslmediaembed', 'plg_editors-xtd_bslmedia' => 'bslmedia'] as $folder => $name) {
        $provider = require $root . '/' . $folder . '/services/provider.php';
        $container = new \Joomla\DI\Container();
        $provider->register($container);
        if ($container->plugin->configuration['name'] !== $name
            || $container->plugin->configuration['params'] !== '{"keep":1}'
            || !$container->plugin->application->test) {
            throw new \RuntimeException('Provider lost config or application');
        }
    }
    echo 'OK: both providers, ' . ($argv[1] ?? 'new') . " CMSPlugin constructor (stub)\n";
}
