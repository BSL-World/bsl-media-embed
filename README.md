# BSL Media Embed 0.8.1 — development candidate

See [project history / история развития](HISTORY.md) for the reconstructed milestones, original Git commits and acceptance boundaries.

The existing BSL Media editor button now also offers a read-only site-file picker with search, folder/type filters and 30 results per page. See [editor instructions and access rules](package/EDITOR.md). Alexander confirmed creating an article with two shortcodes and checking its frontend output on 0.6.0. Candidate 0.8.1 awaits installation and its own acceptance. All four manifests use version 0.8.1 and date 2026-11-12.

The Joomla media library has one folder view with breadcrumbs: open a folder, view its files, upload here or create a subfolder here. Root location and file limits remain in collapsible administrator settings. The old two-level restriction is replaced by technical limits of 32 levels and 20,000 scanned entries. Multi-file MP3/MP4/Opus upload and shortcode copying remain available. There are no delete, rename, move or overwrite operations, even for administrators. Read [configuration, ACL and migration](package/LIBRARY.md). This and the earlier candidates are unpublished.

Joomla 5/6 content renderer and BSL Media editor button. Not yet accepted on an isolated Joomla copy.

Authors click **BSL Media**, paste a share link or official iframe, inspect the shortcode, and click Insert.
The dialog does not load the provider or execute pasted HTML. Titles and artists may be written in the article.

```text
{bslyandexmusic album="23303538" track="91309875"}
{bslyoutube video="ZyBl3noTNSs"}
```

The migration shortcode for article 59 is exactly:

```text
{bslyandexmusic album="3513" track="42612"}
```

Existing `{bslaudio file="key"}` / `{bslvideo file="key"}` behavior is unchanged.

## Accepted input

- Yandex: HTTPS `music.yandex.ru/album/ID/track/ID`; official iframe `/iframe/album/ID/track/ID`.
- YouTube: HTTPS `youtu.be/ID`, `youtube.com/watch?v=ID`, `/shorts/ID`, `/embed/ID`; `www.youtube.com` is also accepted.
- Official YouTube iframe: `youtube.com/embed/ID`, `www.youtube.com/embed/ID`, `www.youtube-nocookie.com/embed/ID`.
- IDs: positive decimal Yandex IDs, 1–20 digits, no leading zero; YouTube ID: 11 ASCII letters, digits, `_` or `-`.
- Tracking `utm_*` and `si` are discarded. YouTube `t` / `start` are discarded with a warning. Other parameters, duplicate parameters, fragments, playlists, credentials, explicit ports, HTTP, encoded paths and dot segments are rejected.
- Paste a single iframe, not a whole page or `{source}` block. Iframe attributes are not copied. Only safe text / Yandex fallback anchors are allowed inside the iframe. Input is limited to 32768 UTF-16 code units; URLs to 4096 code units.
- Saved provider shortcodes accept only their documented attributes in double quotes, with whitespace separating attributes. Duplicates and unknown attributes are rejected. Invalid shortcodes remain visible for correction.

## Build and checks

Requires Python 3.10+, Node 20+ and PHP 8.1+; no downloaded dependencies.

```powershell
python tools/build.py
python tests/package_test.py
node --test tests/parser.test.mjs
& ./tools/check-php.ps1
python tests/legacy-regression.py
```

ZIP entries are sorted, UTF-8 text is normalised to LF, timestamps and permissions are fixed. Output in ignored `dist/`:
`pkg_bslmediaembed-0.5.2.zip`, three standalone extension ZIPs, package SHA-256 and candidate package update XML.
Use the **package ZIP** for installations. Reproducibility is verified twice in the same Python/zlib runtime; cross-version compression output may differ.

Browser checks (standalone parser/dialog with a mocked editor API, **not Joomla/JCE integration**):

```powershell
# Separate terminal, from repository root:
python -m http.server 8769 --bind 127.0.0.1
# Windows, Chrome installed at the path in the script:
& ./tools/browser-check.ps1
```

Results: `dist/browser-dom.html`, `dist/browser-errors.log`. The browser uses a separate test profile under `dist/`.

## Installation, updates and acceptance

See [package/UPGRADE.md](package/UPGRADE.md) and the exact [isolation procedure](package/ISOLATED-ACCEPTANCE.md). Do not deploy this candidate to the working site or disable Sourcerer until isolated acceptance is complete.

See [optional player formatting](package/FORMATTING.md) for text wrapping, width, spacing and narrow-screen behaviour.
