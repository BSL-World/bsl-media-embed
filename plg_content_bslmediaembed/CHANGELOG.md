# Changelog

All notable changes to BSL Media Embed are documented in this file.

## [0.8.0] - unpublished development candidate, 2026-11-12

- Optional Formatting section in the existing editor dialog: normal/centred/left/right placement, pixel width and space from text.
- Bounded align/width/gap shortcode parameters for local audio/video, Yandex Music and YouTube; defaults retain previous output.
- Scoped frontend CSS with desktop wrapping and a narrow-screen reset. Paragraph-safe wrapper and fixed CSS variables; no raw HTML/CSS input.
- Formatted previews and editor insertion preserve options; media library/upload behaviour is unchanged.

## [0.7.0] - unpublished development candidate, 2026-11-12

- One media-library folder view with breadcrumbs, current-folder files/search/sorting, Upload here and inline Create subfolder.
- Removed the separate upload destination dialog and folder-filter dropdown. Root location and size settings are collapsible and admin-only.
- Replaced the two-level product restriction with shared technical limits: 32 levels and 20,000 scanned entries, without following symlink directories.
- Preserve existing keys; discover deeper files consistently in the component, editor and streaming endpoint. Incomplete catalogues are explicitly reported.

## [0.6.0] - unpublished development candidate, 2026-11-11

- Existing editor button offers local file search, folder/type filters and bounded pages alongside Yandex Music / YouTube.
- Authenticated read-only catalogue and saved-cursor insertion; consistent versions, manifest dates and script-cache versioning.
- On 2026-11-12 upgrade/IDs/settings were verified on supplied Joomla 6. Alexander confirmed a new article with two shortcodes, frontend output and component settings; separate per-format audible/seek tests were not reported.

## [0.5.2] - unpublished development candidate, 2026-11-11

- Clear main media folder/upload destination labels and help.
- Client-side folder filter and name/size/modification-time sorting in both directions.
- File modification date uses Joomla user timezone; not advertised as upload/addition time.
- Removed redundant duplicate-stem warnings beside canonical shortcodes; ambiguity checks retained.

## [0.5.1] - unpublished development candidate, 2026-11-11

- File selection starts upload immediately; no duplicate chooser/Upload controls.
- Destination selection and folder creation share a bounded web dialog; explicit depth-two explanation.
- Decimal MB/GB settings; legacy byte limits preserved until changed by administrator.
- Removed destination dropdown/Open button and redundant technical-key column.

## [0.5.0] - unpublished development candidate, 2026-11-11

- Main-page root/size settings, bounded server folder picker, single file rows and shortcode copying.
- Two folder levels and sequential multi-file upload with per-file outcomes.
- Joomla create/upload ACL, CSRF, format/size validation and atomic no-overwrite publishing.
- No delete, rename, move or overwrite operations for any role.

## [0.4.1] - unpublished development candidate, 2026-11-11

- Automatic direct-folder MP3/MP4/Opus discovery and administrative key listing, no required JSON map.
- Relative media root paths resolve against Joomla root; saved 0.4.0 aliases retained.
- Duplicate basenames fail closed (409); Unicode names use generated keys and encoded attachment filenames.

## [0.4.0] - unpublished development candidate, 2026-11-11

- Package includes com_bslmedia for administrator-mapped public MP3/MP4 files.
- Local shortcode syntax and player markup remain; generated URLs use the component endpoint.
- Single byte ranges, HEAD, attachment downloads and bounded streaming; deny-by-default root/map.
- Deployment requires configuration and Joomla 5/6 acceptance. No production migration or publication performed.

## [0.3.0] - development candidate, 2026-11-08

### Added

- Strict Yandex Music and YouTube shortcode rendering with fixed provider URLs.
- BSL Media editors-xtd button: convert supported share URLs or official iframes to shortcodes without executing input HTML.
- Combined Joomla package, deterministic ZIP build, parser/dialog/security and rendering regression tests.

### Validation boundary

- Local PHP/JavaScript and standalone browser checks do not establish real Joomla installation, JCE compatibility or provider playback. Isolated acceptance is pending.
- Existing local audio/video shortcode behavior is preserved.
- Candidate review: corrected editor asset URLs for Joomla in a subdirectory, source-mode selection handling and parser cache versioning; restricted ZIP payloads to manifest-declared files. Isolated Joomla installation and playback remain pending.

## [0.2.0] - 2026-09-06

### Added

- Editor-safe `{bslaudio ...}` shortcode.
- Responsive HTML5 audio player with optional maximum width.
- Automated rendering tests for audio and video shortcodes.

### Changed

- Unified audio and video shortcode processing while preserving existing video behavior.

## [0.1.1] - 2026-09-04

### Changed

- Completed Joomla Update System delivery configuration.

### Note

- No functional changes to shortcode rendering or video playback.

## [0.1.0] - 2026-09-04

### Added

- Editor-safe `{bslvideo ...}` shortcode.
- Responsive HTML5 video player generated during Joomla content preparation.
- Logical media key validation.
- Optional `width` and `height` shortcode attributes.
- English and Russian localization.
- Joomla 5 and Joomla 6 compatibility.
- Joomla Update System metadata.
