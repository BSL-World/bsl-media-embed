<?php

/**
 * @package    BSL.MediaEmbed
 * @subpackage plg_content_bslmediaembed
 *
 * @copyright  Copyright (C) 2026 Vasilyev Alexander (BSL-World.ru).
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace BSL\Plugin\Content\BslMediaEmbed\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

final class BslMediaEmbed extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    private const DEFAULT_VIDEO_WIDTH = 360;
    private const DEFAULT_VIDEO_HEIGHT = 240;
    private const DEFAULT_AUDIO_WIDTH = 640;
    private const MAX_DIMENSION = 4096;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'replaceMediaShortcodes',
        ];
    }

    public function replaceMediaShortcodes(ContentPrepareEvent $event): void
    {
        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        $item = $event->getItem();

        if (!is_object($item) || !isset($item->text) || !is_string($item->text)) {
            return;
        }

        if (
            stripos($item->text, '{bslvideo') === false
            && stripos($item->text, '{bslaudio') === false
            && stripos($item->text, '{bslyandexmusic') === false
            && stripos($item->text, '{bslyoutube') === false
        ) {
            return;
        }

        $result = preg_replace_callback(
            '~\{bsl(video|audio|yandexmusic|youtube)\s+([^{}]+)\}~i',
            fn(array $matches): string => $this->renderMedia(
                strtolower($matches[1]),
                $matches[0],
                $matches[2]
            ),
            $item->text
        );

        if (is_string($result)) {
            $item->text = $result;
        }
    }

    private function renderMedia(
        string $mediaType,
        string $shortcode,
        string $attributeText
    ): string {
        if ($mediaType === 'yandexmusic' || $mediaType === 'youtube') {
            return $this->renderProvider($mediaType, $shortcode, $attributeText);
        }

        $attributes = $this->parseAttributes($attributeText);
        $file = $attributes['file'] ?? '';
        $layout = $this->parseLayout($attributes, $mediaType);
        if ($layout === false) return $shortcode;

        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$~', $file)) {
            return $shortcode;
        }

        $escapedSource = htmlspecialchars(
            $this->buildSource($file),
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );

        if ($mediaType === 'audio') {
            $width = $this->parseDimension(
                $attributes['width'] ?? '',
                self::DEFAULT_AUDIO_WIDTH
            );

            return $this->wrapLayout(sprintf(
                '<audio class="bsl-media-audio" src="%s" controls="controls" preload="none" style="width: 100%%; max-width: %dpx;"></audio>',
                $escapedSource,
                $width
            ), $layout);
        }

        $width = $this->parseDimension(
            $attributes['width'] ?? '',
            self::DEFAULT_VIDEO_WIDTH
        );
        $height = $this->parseDimension(
            $attributes['height'] ?? '',
            self::DEFAULT_VIDEO_HEIGHT
        );

        return $this->wrapLayout(sprintf(
            '<video class="bsl-media-video" src="%s" controls="controls" preload="none" playsinline="playsinline" width="%d" height="%d" style="max-width: 100%%; height: auto;"></video>',
            $escapedSource,
            $width,
            $height
        ), $layout);
    }

    private function renderProvider(string $type, string $shortcode, string $text): string
    {
        $attributes = [];
        while ($text !== '') {
            if (!preg_match('~^\s*([a-z]+)="([^"<>]*)"(\s+|$)~i', $text, $match)) {
                return $shortcode;
            }
            $key = strtolower($match[1]);
            if (isset($attributes[$key])) {
                return $shortcode;
            }
            $attributes[$key] = $match[2];
            $text = substr($text, strlen($match[0]));
        }

        $layout = $this->parseLayout($attributes, $type);
        if ($layout === false) return $shortcode;
        $attributes = array_diff_key($attributes, array_flip(['align', 'width', 'gap']));
        ksort($attributes);
        if ($type === 'youtube') {
            if (array_keys($attributes) !== ['video']
                || !preg_match('~^[A-Za-z0-9_-]{11}$~D', $attributes['video'])) {
                return $shortcode;
            }
            $source = htmlspecialchars('https://www.youtube.com/embed/' . $attributes['video'], ENT_QUOTES, 'UTF-8');
            return $this->wrapLayout('<iframe class="bsl-media-youtube" title="YouTube" src="' . $source
                . '" width="640" height="360" style="border:0;width:100%;height:auto;aspect-ratio:16/9;"'
                . ' allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen="allowfullscreen"'
                . ' referrerpolicy="strict-origin-when-cross-origin"></iframe>', $layout);
        }

        if (array_keys($attributes) !== ['album', 'track']) {
            return $shortcode;
        }
        foreach ($attributes as $id) {
            if (!preg_match('~^[1-9][0-9]{0,19}$~D', $id)) {
                return $shortcode;
            }
        }
        $path = '/album/' . $attributes['album'] . '/track/' . $attributes['track'];
        $source = htmlspecialchars('https://music.yandex.ru/iframe' . $path, ENT_QUOTES, 'UTF-8');
        return $this->wrapLayout('<iframe class="bsl-media-yandexmusic" title="Yandex Music" frameborder="0"'
            . ' allow="clipboard-write" style="border:none;width:100%;height:122px;" width="100%" height="122"'
            . ' src="' . $source . '"><a href="https://music.yandex.ru' . $path
            . '?utm_source=web&amp;utm_medium=copy_link">Yandex Music</a></iframe>', $layout);
    }

    /** null preserves legacy HTML exactly; false rejects malformed new formatting. */
    private function parseLayout(array $attributes, string $type): array|false|null
    {
        $provider = in_array($type, ['youtube', 'yandexmusic'], true);
        if (!isset($attributes['align']) && !isset($attributes['gap']) && !($provider && isset($attributes['width']))) return null;
        $align = $attributes['align'] ?? 'none';
        if (!in_array($align, ['none', 'center', 'left', 'right'], true)) return false;
        $default = $type === 'video' ? self::DEFAULT_VIDEO_WIDTH : self::DEFAULT_AUDIO_WIDTH;
        $width = $attributes['width'] ?? (string) $default;
        $gap = $attributes['gap'] ?? '16';
        if (!preg_match('~^[0-9]{1,4}$~D', $width) || (int)$width < 1 || (int)$width > self::MAX_DIMENSION
            || !preg_match('~^[0-9]{1,3}$~D', $gap) || (int)$gap > 100) return false;
        return ['align'=>$align, 'width'=>(int)$width, 'gap'=>(int)$gap];
    }

    private function wrapLayout(string $html, ?array $layout): string
    {
        if ($layout === null) return $html;
        $assets = $this->getApplication()->getDocument()->getWebAssetManager();
        if (!$assets->assetExists('style', 'plg_content_bslmediaembed.layout')) {
            $assets->registerStyle('plg_content_bslmediaembed.layout', Uri::root() . 'media/plg_content_bslmediaembed/layout.css', ['version'=>'0.8.3']);
        }
        $assets->useStyle('plg_content_bslmediaembed.layout');
        // Only enums and bounded integers enter attributes/CSS, never raw shortcode text.
        return '<span class="bsl-media-layout bsl-media-align-' . $layout['align']
            . '" style="--bsl-media-width: ' . $layout['width'] . 'px; --bsl-media-gap: ' . $layout['gap'] . 'px;">'
            . $html . '</span>';
    }

    private function buildSource(string $file): string
    {
        $query = http_build_query(
            ['option' => 'com_bslmedia', 'task' => 'media.stream', 'file' => $file],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return Uri::root(true) . '/index.php?' . $query;
    }

    private function parseAttributes(string $attributeText): array
    {
        $attributes = [];

        preg_match_all(
            '~([a-z][a-z0-9_-]*)\s*=\s*"([^"]*)"~i',
            $attributeText,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = trim($match[2]);
        }

        return $attributes;
    }

    private function parseDimension(string $value, int $default): int
    {
        if ($value === '' || !ctype_digit($value)) {
            return $default;
        }

        $dimension = (int) $value;

        if ($dimension < 1 || $dimension > self::MAX_DIMENSION) {
            return $default;
        }

        return $dimension;
    }
}
