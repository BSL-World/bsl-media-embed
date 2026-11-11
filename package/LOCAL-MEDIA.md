# Local media — unpublished candidate 0.4.0

HISTORICAL 0.4.0 workflow only. Current folder discovery, configuration, public-access semantics and Opus support are documented in [AUTO-MEDIA.md](AUTO-MEDIA.md). Do not configure a new JSON map for 0.4.1.

HISTORICAL 0.4.0 workflow only. Current folder discovery, configuration, public-access semantics and Opus support are documented in [AUTO-MEDIA.md](AUTO-MEDIA.md). Do not configure a new JSON map for 0.4.1.

Install the package over 0.3.0 without uninstalling its children. It adds com_bslmedia; plugin identities stay unchanged. Components → BSL Media opens Joomla Options, requiring core.admin. Settings live in the component extension params. Set an existing absolute media root and a JSON object of explicit public key-to-relative-file mappings:

```json
{"song":"song.mp3","clip":"videos/clip.mp4"}
```

An external root is supported. PHP needs read/traverse access, never write access. Installation creates no media folder or OS permissions. Empty root returns 503; empty/invalid map grants no keys. Only mapped keys are public: links do not inherit article ACL. Never map private files. Unknown keys/missing files return 404; invalid keys 400; forbidden paths 403. Only MP3/MP4 delivery is implemented. PDF, ZIP, DOCX and distributions remain BSL Download Service's responsibility.

Mapping paths permit ASCII letters/numbers, space, underscore, dash, dot and relative slash, with lowercase .mp3/.mp4 suffixes. Absolute paths, dot-dot, percent encodings, backslashes, wrappers and escaping symlinks are rejected. In-root symlinks are allowed. MIME follows the allowed extension; administrators must supply genuine media. nosniff is set. Roots/parent directories must be writable ONLY by trusted administrators. Pre/post-open realpath checks and descriptor metadata reduce races, but portable PHP cannot guarantee security against a hostile concurrent filesystem writer. Windows junction/reparse behaviour and file replacement need target-host acceptance.

No article edits are required. The renderer now generates index.php?option=com_bslmedia&task=media.stream&file=KEY. Append &download=1 for attachment disposition/MP3 download. Existing keys MUST be mapped before production upgrade.

GET/HEAD only; other methods return 405 with Allow. GET supports a single closed, open-ended or suffix byte range. Invalid/multipart/overflowing/unsatisfiable ranges return 416 with Content-Range bytes */SIZE. Valid ranges return 206 and exact Content-Length/Content-Range. HEAD ignores Range and sends full length without a body. Accept-Ranges is bytes. Streaming uses at most 64 KiB per read and requires 64-bit PHP. Cache-Control is no-store; ETag/If-Range caching is not implemented. Web server/proxy compression must not alter response lengths. Do not replace files during transfer.

Upgrade/reinstall must preserve component params and existing plugin IDs/state/params; verify on real Joomla 5/6. Package child uninstall is blocked. Removing the package removes extension code, not external administrator-owned media. Uninstall is not rollback: restore the agreed files/DB/settings backup instead. Verify uninstall on a backed-up provided test site only as a separate agreed step.

## Separate production migration plan — do not execute yet

1. Back up production files/DB/settings and inventory every download.php consumer: existing media keys, document/distribution URLs, feeds and external links.
2. Install the accepted package, configure root/map for every existing key; preserve article text. Check 200, HEAD, 206, 416, MP3 download, articles, cache, JCE/TinyMCE/Manager and provider embeds.
3. Alexander verifies audible playback, time progression, seek, pause/resume, mobile/fullscreen. Verify documents/distributions separately through BSL Download Service.
4. Keep old download.php until all dependencies are accounted for. Delete it only after separate approval proving no consumers remain. Never automatically copy the private gateway into the product.
5. Roll back by restoring agreed files/DB/settings and caches, retaining external media; do not remove the package as a shortcut.

JED/open gates: real install/upgrade/reinstall/uninstall on Joomla 5/6, ACL and translations, actual HTTP HEAD/Range and compression, open_basedir/read permissions, symlinks/junctions, sound/download confirmation. Local request planner tests are not HTTP or playback acceptance. No ZIP/feed publishing before acceptance.
