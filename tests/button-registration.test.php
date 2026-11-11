<?php
declare(strict_types=1);
namespace Joomla\CMS\Plugin {
    class CMSPlugin {
        protected $_name = 'bslmedia';
        public static object $app;
        public function getApplication(): object { return self::$app; }
    }
}
namespace Joomla\CMS\Editor\Button {
    class Button {
        public function __construct(public string $name, public array $props, public array $options) {}
    }
}
namespace Joomla\CMS\Language {
    class Text { public static function script(string $key): void {} }
}
namespace Joomla\CMS\Uri {
    class Uri { public static function root(bool $pathOnly = false): string { return $pathOnly ? '/joomla' : 'https://example.test/joomla/'; } }
}
namespace Joomla\CMS\Event\Editor {
    class EditorButtonsSetupEvent {
        public function __construct(private array $disabled, private object $registry, private string $id) {}
        public function getDisabledButtons(): array { return $this->disabled; }
        public function getButtonsRegistry(): object { return $this->registry; }
        public function getEditorId(): string { return $this->id; }
    }
}
namespace Joomla\Event {
    interface SubscriberInterface { public static function getSubscribedEvents(): array; }
}
namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/plg_editors-xtd_bslmedia/src/Extension/BslMedia.php';
    $assets = new class {
        public array $scripts = [];
        public array $used = [];
        public function assetExists($type, $name): bool { return isset($this->scripts[$name]); }
        public function registerScript($name, $uri, $options, $attributes, $dependencies): void {
            $this->scripts[$name] = [$uri, $attributes, $dependencies];
        }
        public function useScript($name): void { $this->used[$name] = true; }
    };
    \Joomla\CMS\Plugin\CMSPlugin::$app = new class ($assets) {
        public function __construct(private object $assets) {}
        public function getDocument(): object { return $this; }
        public function getWebAssetManager(): object { return $this->assets; }
        public function isClient(string $name): bool { return $name==='administrator'; }
        public array $options=[];
        public function addScriptOptions(string $name,array $value): void { $this->options[$name]=$value; }
    };
    $registry = new class {
        public array $buttons = [];
        public function add($button): void { $this->buttons[] = $button; }
    };
    $plugin = new \BSL\Plugin\EditorsXtd\BslMedia\Extension\BslMedia();
    $plugin->onEditorButtonsSetup(new \Joomla\CMS\Event\Editor\EditorButtonsSetupEvent(['bslmedia'], $registry, 'disabled'));
    if ($registry->buttons || $assets->scripts) { throw new \RuntimeException('Disabled button was registered'); }
    foreach (['article', 'second'] as $id) {
        $plugin->onEditorButtonsSetup(new \Joomla\CMS\Event\Editor\EditorButtonsSetupEvent([], $registry, $id));
    }
    if (array_map(fn($button) => $button->options['editor'], $registry->buttons) !== ['article', 'second']) {
        throw new \RuntimeException('Editor IDs were not preserved');
    }
    $asset = $assets->scripts['editor-button.bslmedia'];
    if ($asset !== ['https://example.test/joomla/media/plg_editors-xtd_bslmedia/button.js', ['type' => 'module'], ['editors']]
        || !isset($assets->used['editor-button.bslmedia']) || count($assets->scripts) !== 1
        || $registry->buttons[0]->props['action'] !== 'bsl-media') {
        throw new \RuntimeException('Wrong asset, action or duplicate registration');
    }
    echo "OK: button registration, disabled buttons, subdirectory assets and editor IDs\n";
    // Optional: exercise the real WAM item class without bootstrapping Joomla or reading configuration.php.
    if (isset($argv[1])) {
        require $argv[1] . '/libraries/src/WebAsset/WebAssetItemInterface.php';
        require $argv[1] . '/libraries/src/WebAsset/WebAssetItem.php';
        $item = new \Joomla\CMS\WebAsset\WebAssetItem('editor-button.bslmedia', $asset[0]);
        if ($item->getUri() !== $asset[0]) { throw new \RuntimeException('WAM changed the subdirectory asset URL'); }
        echo "OK: real WAM class resolved full subdirectory URL; no site bootstrap\n";
    }
}
