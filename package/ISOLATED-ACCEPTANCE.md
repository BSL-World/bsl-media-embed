# Isolated Joomla acceptance — preparation gate

Status on 2026-11-09: **no safe copy confirmed**. Read-only inspection found `C:\OSPanel\home\bsl-world.local` and `bsl-world-dr.local`. Both configurations have `mailonline=true`, `offline=false`. Their DB host values are not literal loopback addresses (they may be OSPanel aliases; no remote/production connection is inferred or attempted). Separate restricted DB credentials, clean menu 101, disabled tasks/integrations and outbound firewall rules have not been established. Neither site's pages, CLI bootstrap nor database were opened for this review.

Do not use those existing sites as acceptance targets merely because their domains are local. The following is a preparation procedure, **not an executed migration**.

## Prepare before the first Joomla request

1. Create a new disposable VM, e.g. `mediaembed-accept-20261109`, with a snapshot before import. Keep its web service stopped. Use PHP 8.4 compatible with the restored Joomla core and a local MySQL/MariaDB. Bind the database only to loopback. Do not share production disks, DB sockets or credentials. Serve the site only on a private VM interface, with any host forwarding bound to `127.0.0.1`; do not expose it publicly. Use a separate browser profile and a local `.test` host.
2. Before extracting or booting PHP, configure default-deny outbound rules for the VM's web/PHP/CLI processes (HTTP/S, SMTP, DNS and other integrations). Permit only its own local DB. Keep the browser offline/default-deny too during the first inspection. Do not copy OS cron/Task Scheduler jobs or run Joomla CLI tasks. Preserve firewall logs as evidence. No test should request the address formerly injected into menu 101.
3. Create a new database `mediaembed_accept_20261109` and a unique user restricted to that schema, without global grants or access to production. Import a verified backup into this DB only. Keep the backup read-only; record its hash privately. Do not start the old configuration to learn its settings. Inventory dump views/triggers/events/routines and remove/disable copied event execution or external destinations before starting the service. Do not enable the MySQL event scheduler.
4. Extract site files into a new private VM directory. Replace `configuration.php` before loading it: point host/db/user/password solely at the new schema/user; set `mailonline=false`, `offline=true`, `live_site=''`, empty copied cookie domain/path, new random `secret`, clone-only log/tmp paths and suitable local TLS settings. Remove copied SMTP/API/payment/webhook/cloud backup/SSO credentials. Keep secrets out of the report and screenshots. Invalidate copied sessions/remember-me credentials in the clone. Do not change production accounts.
5. Through the DB client, first locate the actual prefix and check `JSON_VALID(params)` for menu 101 and component `com_scheduler`. Stop on invalid/missing/unexpected data. Back up these rows privately, then apply the following **only to the isolated DB**, replacing `pfx_` with its verified prefix. Never run this SQL against a working site:

```sql
START TRANSACTION;
UPDATE pfx_menu
SET params = JSON_SET(params, '$.helixultimatemenulayout', '')
WHERE id = 101 AND JSON_VALID(params);

UPDATE pfx_scheduler_tasks SET state = 0;
UPDATE pfx_extensions SET enabled = 0
WHERE type = 'plugin' AND
  (folder = 'task' OR
   (folder = 'system' AND element IN ('schedulerunner', 'tasknotification')));

UPDATE pfx_extensions
SET params = JSON_MERGE_PATCH(params,
  '{"lazy_scheduler":{"enabled":0},"webcron":{"enabled":0,"key":""}}')
WHERE type = 'component' AND element = 'com_scheduler' AND JSON_VALID(params);

SELECT id,
  COALESCE(JSON_UNQUOTE(JSON_EXTRACT(params, '$.helixultimatemenulayout')), '') = '' AS menu_layout_empty
FROM pfx_menu WHERE id = 101;
SELECT COUNT(*) AS enabled_tasks FROM pfx_scheduler_tasks WHERE state <> 0;
SELECT element, folder, enabled FROM pfx_extensions
WHERE type = 'plugin' AND
  (folder = 'task' OR
   (folder = 'system' AND element IN ('schedulerunner', 'tasknotification')));
SELECT JSON_EXTRACT(params, '$.lazy_scheduler.enabled') AS lazy_enabled,
       JSON_EXTRACT(params, '$.webcron.enabled') AS webcron_enabled
FROM pfx_extensions WHERE type = 'component' AND element = 'com_scheduler';
COMMIT;
```

The menu query must return exactly row 101 with `menu_layout_empty=1`, tasks count 0, all selected plugins disabled and both scheduler flags 0. A pre-import malicious layout must never be restored by a subsequent rollback. Preserve other menu/article content. Do not print the old injected value or webcron key.

6. Inventory all copied enabled extensions and external endpoints statically. Disable notification, backup, webhook, analytics, payment, SSO, sync and other outbound integrations in the clone; record each change. Keep firewall denial even if a setting was missed. Leave content 0.2.0, JCE/TinyMCE, Sourcerer and Daily Content available for the planned baseline unless a reviewed integration requires disabling. The clone's `download.php` must resolve local fixtures, not production registries.
7. Before the first request record: clone path/URL, Joomla/PHP/DB/JCE versions; separate DB/schema identity and restricted grants (no credentials); menu verification; mail disabled; task/webcron settings; list of disabled integrations; firewall policy and denial test/log; stopped external jobs. Verify DB host resolution inside the VM and that production DB destinations are unreachable. Only after this gate is documented may Joomla boot on the private interface. Check for any remaining outbound attempts with the browser still restricted. An unverified item stops dynamic acceptance.

## Acceptance after the isolation gate

1. Capture baseline content 0.2.0 extension ID, enabled/access/ordering/params, update associations and article 59's exact source block. Record the working Sourcerer player's ID/appearance; permit only reviewed official-provider traffic in the dedicated browser for manual sound testing. Keep server egress blocked. If playback is blocked, record the environment restriction instead of claiming a plugin failure or success.
2. Install the current **package ZIP** through Joomla over existing content 0.2.0. Record the installed versions, unchanged content ID/settings/state and package ownership of both children. Repeat installation; disable the button and verify an update does not re-enable it. Test fresh installation separately (content requires explicit enabling).
3. In JCE and TinyMCE on Joomla 5/6, test share URL and official iframe input for both providers, invalid input, cancel/Escape, target editor, selected text/caret, visual/source mode, two fields and mobile width. Save/reopen as Manager; the article must contain only the canonical shortcode. No execution/iframe privileges are needed for the saved content.
4. Replace only the known article 59 block with `{bslyandexmusic album="3513" track="42612"}`. Temporarily disable Sourcerer system 300 and editors-xtd 301 on the clone. Clear clone cache. Check article views and RU/EN home pages, no raw shortcode/source markers or duplicate player, album 3513 / track 42612, local audio/video and Daily Content. Regular Labs Library is not removed.
5. Manually verify sound, progressing time, pause/resume for Yandex and YouTube, fullscreen and narrow layout; record browser/date/account/region/network constraints and console/network errors. An iframe/HTTP 200 alone does not satisfy playback acceptance.
6. On a disposable snapshot, test discovery of a newer package through a **local test feed**, coordinated update and state preservation. Test partial failure, rollback and package uninstall without touching published feeds. A successful ZIP structure check is not an installation test.

Capture results in the main development journal. Working-site migration, disabling/removing Sourcerer, publication of ZIP/feed, Spotify and JED remain outside this step.
