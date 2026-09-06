<?php

declare(strict_types=1);

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        private object $application;

        public function __construct()
        {
            $this->application = new class () {
                public function isClient(string $client): bool
                {
                    return $client === 'site';
                }
            };
        }

        public function getApplication(): object
        {
            return $this->application;
        }
    }
}

namespace Joomla\CMS\Event\Content {
    class ContentPrepareEvent
    {
        public function __construct(private object $item)
        {
        }

        public function getItem(): object
        {
            return $this->item;
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static function root(bool $pathOnly = false): string
        {
            return '/joomla';
        }
    }
}

namespace Joomla\Event {
    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }
}

namespace {
    use BSL\Plugin\Content\BslMediaEmbed\Extension\BslMediaEmbed;
    use Joomla\CMS\Event\Content\ContentPrepareEvent;

    define('_JEXEC', 1);

    require dirname(__DIR__)
        . '/plg_content_bslmediaembed/src/Extension/BslMediaEmbed.php';

    function renderText(string $text): string
    {
        $item = (object) ['text' => $text];
        $plugin = new BslMediaEmbed();
        $plugin->replaceMediaShortcodes(new ContentPrepareEvent($item));

        return $item->text;
    }

    function expectContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Expected fragment not found: ' . $needle);
        }
    }

    $video = renderText('{bslvideo file="short-video-01" width="640" height="360"}');
    expectContains('<video class="bsl-media-video"', $video);
    expectContains('file=short-video-01&amp;source=site', $video);
    expectContains('width="640" height="360"', $video);

    $audio = renderText('{bslaudio file="manifest-audio"}');
    expectContains('<audio class="bsl-media-audio"', $audio);
    expectContains('file=manifest-audio&amp;source=site', $audio);
    expectContains('max-width: 640px;', $audio);

    $customAudio = renderText('{bslaudio file="manifest-audio" width="480"}');
    expectContains('max-width: 480px;', $customAudio);

    $invalid = '{bslaudio file="../manifest.mp3"}';

    if (renderText($invalid) !== $invalid) {
        throw new RuntimeException('Invalid file key was not preserved');
    }

    $combined = renderText(
        '{BSLAUDIO file="manifest-audio"} {BSLVIDEO file="short-video-01"}'
    );
    expectContains('<audio class="bsl-media-audio"', $combined);
    expectContains('<video class="bsl-media-video"', $combined);

    echo "OK: media shortcode rendering tests passed\n";
}
