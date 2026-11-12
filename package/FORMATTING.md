# Player formatting — candidate 0.8.2

In an article, place the cursor and click **BSL Media**. Select a site file or paste a Yandex Music/YouTube link. Expand **Formatting** if needed:

- **Placement:** Normal, Centred, Left with text wrapping, or Right with text wrapping.
- **Width:** optional whole number in pixels, 1–4096. Empty keeps the player's existing/default width.
- **Space from text:** optional whole number in pixels, 0–100. A formatted block uses 16px when empty. This is an outside margin, not padding inside the player.

The shortcode preview updates immediately. Invalid numeric input disables Insert. Options apply to the selected player and remain if switching between site files and links. Save/reopen keeps them in the article shortcode; the dialog inserts new players and does not edit an existing shortcode in place. To change an existing one, adjust its attributes in the article or replace it using the dialog.

Examples:

```text
{bslvideo file="clip.mp4" align="left" width="320" gap="20"}
{bslaudio file="song.mp3" align="center" width="480"}
{bslyoutube video="ZyBl3noTNSs" align="right" width="360" gap="16"}
{bslyandexmusic album="3513" track="42612" width="480"}
```

At viewport widths of 768px and above, left/right players float with spacing on the text side and below; centred players have automatic side margins. Width is always constrained by the content container. Normal placement has no float. Formatted video without width uses 360px; formatted audio/provider players use 640px. Existing manually written width-only local audio/video retains its earlier rendering rules for compatibility. When new width/spacing options are chosen in the dialog with Normal placement, the dialog emits align="none" explicitly so the new mobile reset applies too.

Below 768px, formatted blocks stop floating, occupy the content width and use a standard 1rem bottom margin. Desktop width and text-gap settings are therefore not forced onto small screens. YouTube retains 16:9 proportions, Yandex Music its provider height, and local video its existing responsive height behaviour. Container layout, theme styles, embedded modules and very narrow desktop columns still require visual acceptance in the actual site theme.

Default insertion emits no formatting parameters and retains previous HTML. Only align/width/gap values validated as enums/bounded integers reach fixed classes and CSS custom properties. No arbitrary style, HTML or URL option is accepted. The content plugin packages media/layout.css and registers its versioned same-site stylesheet through Joomla WebAssetManager only when a formatted player is rendered. The wrapper is a span displayed as a block/float, so a shortcode inside an editor paragraph does not introduce an invalid nested div.

Copy shortcode in the library still copies a basic shortcode; formatting is chosen in the article editor. This candidate adds no new download link. Any future player-associated link must be included in the same layout wrapper.

Acceptance after installation: insert a small left/right player next to several paragraphs, save/reopen, verify actual text wrapping and spacing, then resize below 768px and confirm no float/overflow. Check centred/normal layouts and an old unformatted article too. Repeat for the supported editors/providers as agreed. Automated mocks, server rendering and packaged CSS checks do not prove the appearance or audible playback in a browser. Candidate 0.8.0 is not yet a published release.
