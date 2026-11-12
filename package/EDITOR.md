# Insert media into an article (0.8.0 candidate)

1. In the article editor, place the cursor where the player should appear.
2. Click the existing **BSL Media** editor button.
3. In **Site files**, search by file name or select a folder/type. Click a file, then **Insert**.
4. Save the article and open its public page to check the player.

The picker shows 30 results per page. MP3 and Opus insert audio shortcodes; MP4 inserts a video shortcode. Unicode names and subfolders use the same generated keys as the component's Copy button. The main media folder is the folder configured in Components → BSL Media.

For external media, switch to **Yandex Music / YouTube**, paste a supported share link or official embed code, then click **Insert**. Pasted HTML is parsed, never executed. Switching sections clears the selection. Cancel inserts nothing.

Upload files, create subfolders and configure the root in Components → BSL Media. The editor picker only reads the catalogue and grants no upload or folder-management permissions.

The catalogue endpoint requires a signed-in user with article create/edit/edit-own permission at com_content level, or BSL Media management/upload/create permission. Category-only article permissions are not sufficient in this candidate. Anonymous visitors cannot list files. Admin and frontend endpoints each use the corresponding application's session. Responses contain relative names and shortcodes, never absolute server paths. Result pages are bounded; the server still scans the configured folder on each request.

Automatic tests use fixtures and editor mocks. Actual JCE/TinyMCE placement, source mode, saved articles, Publisher access and playback require acceptance on the supplied Joomla site; they have not been rechecked for this candidate.

Folders deeper than two levels are included. The same technical scan limits apply as in the component; an incomplete-catalogue message is shown when a limit or unreadable folder is encountered.

See [optional player formatting](FORMATTING.md) for text wrapping, width, spacing and narrow-screen behaviour.
