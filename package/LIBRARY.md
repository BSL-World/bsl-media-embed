# BSL Media library — candidate 0.8.3

## One open folder

Components → BSL Media opens the main library folder. Click a subfolder to enter it, or a breadcrumb/Up link to go back. The table shows only media files in the open folder. Search filters that folder's names; Name/Size/Modified sort in either direction. Modified uses the Joomla user's timezone, not a claimed upload date. Copy shortcode remains available.

**Upload files here** always targets the open folder. **Create subfolder** expands an inline form, creates inside the same folder, then opens the new folder. There is no separate upload-destination chooser, folder-filter dropdown or destination dialog. Navigating reloads the normal component page. Folder-tree upload is not supported.

Administrators expand **Library settings** to set the main library folder and per-file limit. Settings expand automatically when unconfigured. Root location is chosen using the folder chooser; there is no manual path field or advanced override. The page shows a human location, such as Beside site → media, instead of a full server path. The one remaining folder dialog changes this root and is available only to administrators. Its single canonical boundary is the folder containing Joomla; a stored media root cannot expand that boundary. If the parent is a filesystem root, forbidden by hosting permissions, or a symlink would expand scope, browsing falls back to the site folder itself. Full server paths are not emitted by the root chooser API or displayed in the chooser. Filesystem roots and escaping links remain rejected. Changing the root requires Save settings and affects the whole public library.

## Nesting and public links

The old two-subfolder product restriction is removed. Shared technical limits are 32 subfolder levels and 20,000 inspected filesystem entries per catalogue scan, including unsupported/hidden entries. Directory iterators bound work without loading every directory name into a PHP array. Directory symlinks are skipped and canonical directories are visited once. Unreadable folders and reached limits mark the catalogue incomplete; the component and editor picker show an explicit message. A missing key after an incomplete scan returns 503 instead of falsely claiming 404. Direct children browsing has the same entry/depth bounds.

Supported MP3/MP4/Opus files in the dedicated root and nested folders are public, independent of article ACL. **Upgrading from 0.6.0 makes files deeper than two levels discoverable/public as well**, within the new bounds. Choose a root containing only public media. Hidden, unsupported and outside-root files are not discovered. Public media links are intended; arbitrary neighbouring paths are not.

Existing full-name keys, unique top-level stems and saved legacy aliases remain unchanged. Nested/Unicode/spaced names use the same deterministic media-HASH of relative path. Renaming or moving files externally changes their generated key. New deeper files appear in the component, editor picker and streaming catalogue under the same key. Existing aliases cannot bypass path validation or the technical depth bound.

## Upload, permissions and limits

Selecting multiple files with Ctrl/Shift/Ctrl+A and clicking Open starts one multipart POST immediately. Cancellation sends nothing. Files are processed sequentially, with individual results, partial failures and detected PHP file-count truncation warnings. No queue, resume or background upload manager is implemented. A request rejected before PHP cannot yield per-file results. JavaScript is required for auto-upload and root browsing; ordinary folder links and the create form use standard requests.

The administrator sets a positive value in decimal MB/GB (1,000,000 / 1,000,000,000 bytes), up to six decimal places. Old binary max_upload_mb values retain exactly their previous byte allowance until explicitly changed. Publisher sees the allowance and cannot change it. PHP/proxy/hosting limits may be stricter and are neither guessed nor overridden.

ACL remains core.admin for root/settings, core.manage for component/menu access, media.create for subfolders and media.upload for uploads. No rights are automatically granted. Give Publisher component access and only the desired actions. Each mutation independently checks POST, action authorization and Joomla CSRF; uploaded-file provenance is checked in the controller. Article-author read-only catalogue permissions do not grant component settings or writes; see [editor instructions](EDITOR.md).

Upload screening checks actual size, file name, extension, finfo and container markers: MP3 audio/mpeg, MP4 ftyp, Ogg Opus OggS/OpusHead. Executable secondary suffixes such as .php.mp4 are rejected. This is not a full codec decoder or antivirus. UTF-8 names, traversal, reserved device names, separators and controls are checked. A hidden owned staging file is copied in bounded chunks and atomically published using a hard link that cannot replace an existing target. No overwrite-capable rename fallback exists. Hard-link support and web-PHP write/traverse rights are required; failure is explicit. Only our unpublished temporary file is cleaned up.

No deletion, renaming, moving or overwriting exists for any role, including administrator. This appears beside the controls and in tooltips. Use a file manager or hosting panel for those actions. User media are not removed on extension uninstall. Keep root and parent writers trusted: portable PHP cannot guarantee race-free operations against malicious concurrent directory replacement.

## Delivery and acceptance

Media delivery supports GET/HEAD, one byte Range with 206/416 metadata, exact lengths, 64 KiB body chunks, nosniff and attachment download=1. It needs 64-bit PHP; proxy compression must not change lengths. Cache-Control is no-store; ETag/If-Range caching is not implemented. Manually placed media remain trusted administrator content.

Install only the complete pkg_bslmediaembed ZIP. Existing IDs/settings must survive an upgrade. 0.8.3 is an unpublished candidate: unit fixtures and mocks do not prove real Joomla navigation, multipart upload, Publisher ACL, visual rendering, audible playback or hosting behaviour. Alexander installs through the supplied Joomla6 interface with an up-to-date Akeeba backup, then Codex reads installed state; each next manual step is agreed separately. Joomla5, uninstall/rollback, symlink/junction and large-file/hosting checks remain separate gates. No environments are created automatically.

Production migration remains separate: inventory every download.php consumer, back up files/DB/settings, preserve old keys, verify existing articles and new component URLs, range/download and editors; keep documents/distributions with BSL Download Service. Do not remove download.php until all consumers are accounted for and Alexander explicitly approves. Restore the agreed backup and cache for rollback while retaining external media. Extension uninstall is not rollback.

See [optional player formatting](FORMATTING.md) for text wrapping, width, spacing and narrow-screen behaviour.

The chooser starts in the current library if it is within the boundary, otherwise in the site folder. Return to site folder is always available. Up stops at the logical container labelled Site and neighbouring folders. Unreadable children are skipped; a failed request preserves the last valid view. Selection changes the human location label, and only Save settings applies it; files are not moved. A hidden boundary-relative selection is revalidated server-side and converted internally into a portable Joomla-relative setting. Absolute or escaping submissions to manage.save are rejected. Existing valid absolute settings are recognized without showing their values and become relative on the next settings save. A stored root outside the allowed chooser area is not rewritten by upgrade; it requires choosing an allowed folder before settings can be saved. Existing aliases, upload limits and ACL remain preserved.
