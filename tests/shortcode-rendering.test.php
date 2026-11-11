<?php

declare(strict_types=1);

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        public static bool $site = true;
        private object $application;

        public function __construct()
        {
            $this->application = new class () {
                public function isClient(string $client): bool
                {
                    return CMSPlugin::$site && $client === 'site';
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
    expectContains('file=short-video-01', $video);
    expectContains('width="640" height="360"', $video);

    $audio = renderText('{bslaudio file="manifest-audio"}');
    expectContains('<audio class="bsl-media-audio"', $audio);
    expectContains('file=manifest-audio', $audio);
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

    $yandex = renderText('{bslyandexmusic album="3513" track="42612"}');
    expectContains('src="https://music.yandex.ru/iframe/album/3513/track/42612"', $yandex);
    expectContains('height="122"', $yandex);
    expectContains('allow="clipboard-write"', $yandex);
    expectContains('utm_source=web&amp;utm_medium=copy_link', $yandex);
    $youtube = renderText('{bslyoutube video="ZyBl3noTNSs"}');
    expectContains('https://www.youtube.com/embed/ZyBl3noTNSs', $youtube);
    expectContains('referrerpolicy="strict-origin-when-cross-origin"', $youtube);
    if (renderText($yandex . $youtube) !== $yandex . $youtube) {
        throw new RuntimeException('Rendering is not idempotent');
    }
    foreach ([
        '{bslyandexmusic album="0" track="42612"}',
        '{bslyandexmusic album="01" track="42612"}',
        '{bslyandexmusic album="3513"}',
        '{bslyandexmusic album="3513" album="1" track="42612"}',
        '{bslyandexmusic album="3513" track="42612" src="https://evil.test"}',
        '{bslyandexmusic album="3513" track="42612" junk}',
        '{bslyandexmusic album="3513"track="42612"}',
        '{bslyandexmusic album="3513" track="42612&#34;"}',
        '{bslyandexmusic album="123456789012345678901" track="1"}',
        '{bslyoutube video="ZyBl3noTNSs" onload="alert(1)"}',
        '{bslyoutube video="ZyBl3noTNSs\""}',
        '{bslyoutube video="short"}',
        "{bslyoutube video='ZyBl3noTNSs'}",
    ] as $bad) {
        if (renderText($bad) !== $bad) {
            throw new RuntimeException('Unsafe shortcode rendered: ' . $bad);
        }
    }
    $mixed = 'Before {bslyandexmusic track="42612" album="3513"} / {bslaudio file="audio"} / {bslvideo file="video"} After';
    $rendered = renderText($mixed);
    expectContains('Before <iframe', $rendered);
    expectContains('<audio', $rendered);
    expectContains('<video', $rendered);
    expectContains('After', $rendered);
    expectContains('max-width: 640px;', renderText('{bslaudio file="audio" width="4097"}'));
    expectContains('width="360" height="240"', renderText('{bslvideo file="video" width="0" height="-1"}'));
    expectContains('/joomla/index.php?option=com_bslmedia&amp;task=media.stream&amp;', $rendered);
    \Joomla\CMS\Plugin\CMSPlugin::$site = false;
    $raw = '{bslyoutube video="ZyBl3noTNSs"}';
    if (renderText($raw) !== $raw) {
        throw new RuntimeException('Administrative content must not be rendered');
    }
    \Joomla\CMS\Plugin\CMSPlugin::$site = true;
    foreach ([(object) [], (object) ['text' => null], (object) ['text' => []]] as $item) {
        (new BslMediaEmbed())->replaceMediaShortcodes(new ContentPrepareEvent($item));
    }
    echo "OK: media shortcode rendering tests passed\n";
}
