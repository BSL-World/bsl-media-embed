# BSL Media Embed

BSL Media Embed is a content plugin for Joomla that replaces editor-safe shortcodes with responsive HTML5 video players.

The plugin is intended for media files delivered through a controlled `download.php` gateway. Only a logical file key is stored in the article, so visual editors such as JCE and TinyMCE do not need to preserve the final `<video>` markup.

## Features

- Editor-safe `{bslvideo ...}` shortcode.
- Responsive HTML5 video player.
- Optional player width and height.
- Strict validation of logical file keys.
- English and Russian localization.
- Joomla 5 and Joomla 6 support.
- Joomla Update System integration.

## Requirements

- Joomla 5.x or Joomla 6.x.
- PHP 8.1 or later.
- A configured `/download.php` gateway on the Joomla site.
- Media files mapped to logical keys by the gateway.

## Usage

Basic shortcode:

```text
{bslvideo file="short-video-01"}
```

Custom initial dimensions:

```text
{bslvideo file="short-video-01" width="640" height="360"}
```

The `file` value is a logical key, not a filesystem path or public media URL. The current version requests the media from:

```text
/download.php?file=short-video-01&source=site
```

## Security model

The plugin does not accept arbitrary filesystem paths. File access remains under the control of the site's `download.php` allowlist and media mapping.

## License

GNU General Public License version 2 or later. See `LICENSE.txt`.

## Author

Vasilyev Alexander — [BSL-World.ru](https://bsl-world.ru)
