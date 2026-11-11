# Transition from content 0.2.0

This candidate contains content `bslmediaembed` and editors-xtd `bslmedia`, both 0.3.0.
The content plugin keeps its original element, folder and namespace. Joomla's plugin installer looks up this identity and updates the existing extension record; the package adapter associates both children with the package ID.
This is source-code analysis, not a completed installation test.

The read-only environment check and exact preparation/acceptance gates are in [ISOLATED-ACCEPTANCE.md](ISOLATED-ACCEPTANCE.md). Existing local copies did not pass the mail/isolation gate; do not boot them as part of this review.

The providers adapt to the typed dispatcher constructor in early Joomla 5 and the config constructor in newer Joomla 5/6. Constructor stubs are tested in both modes; this does not establish full compatibility with every Joomla 5 minor release. The local JCE tree examined is 2.9.99.10. Asset activation is explicit because JCE's toolbar menu and Joomla's button layout look up different asset names. The action passes an explicit editor ID and restores a TinyMCE/JCE selection bookmark or a textarea range before insertion.

1. Prepare an isolated Joomla copy with its own database. Before its first request, clear menu 101's `params.helixultimatemenulayout` and disable mail, scheduled tasks, webcron and external integrations as required by the handoff. Do not run an unclean old backup.
2. Save content plugin 0.2.0 files, its extension ID, enabled/access/ordering/params/update-site associations, article 59's exact block and both Sourcerer states. Also record Joomla, PHP and JCE versions.
3. Use Joomla's installer to install `pkg_bslmediaembed-0.3.0.zip`. Do not uninstall content 0.2.0 first.
4. Verify the original content extension ID, enabled state, params, access and ordering survive; both children have the new package ID and no duplicate content record exists. The button installer enables the button only on first installation; repeat upgrades must preserve an administrator's disabled state. Fresh sites must enable the content plugin explicitly.
5. In JCE, enable the Joomla toolbar menu in the active profile if required, or use the editor's extra buttons. Test insertion, cancel, caret/selection restoration, source mode and two editor instances. Also test TinyMCE on Joomla 5 and 6 without the backward compatibility plugin. No iframe permission or code-execution permission should be required for Manager.
6. Before replacing article 59, record the old player's behavior with Sourcerer. Replace only the identified source block with `{bslyandexmusic album="3513" track="42612"}`; keep the original block externally for rollback. Preserve unrelated article text.
7. On the copy only, disable Sourcerer plugins 300/301, clear cache, save as Manager, and check article/fulltext, RU/EN home views and other views containing the article. Check local audio/video and Daily Content. Do not remove Regular Labs Library.
8. Compare the iframe URL and the track/album IDs, desktop/mobile rendering, no duplicate/raw shortcode or `{source}` output. Manually verify audible sound, time progression, pause and resume for Yandex; verify YouTube playback/fullscreen and provider errors. Permit provider traffic only for that controlled test and record browser/date/network/region/account restrictions. HTTP 200 is insufficient.

## Update channels

- `update-server/updates.xml` remains the **legacy content feed**, at 0.2.0. It is not silently replaced by a package feed. The first migration is an explicit package installation.
- The package manifest registers `https://bsl-world.ru/downloads/bsl-media-embed/package-updates.xml`. `tools/build.py` writes a candidate `dist/package-updates.xml` with the exact ZIP SHA-256 and logical download key. It must be reviewed and published together with the ZIP through the separate release workflow. No feed/download has been published by this task.
- Future releases use type `package`, element `pkg_bslmediaembed`. Keep the legacy content feed frozen while the package owns coordinated updates. Test discovery of a newer package, repeated package installation, preservation of child states and absence of competing child update offers on the isolated copy.
- The package blocks individual child uninstall. Uninstalling the package removes its children and breaks shortcode rendering; it is not a rollback method. Check uninstall behavior only on a disposable test copy. Joomla package installation is not assumed to be transactionally reversible across all child updates.

## Rollback and remaining gates

Before returning to standalone content 0.2.0, test Joomla's downgrade/reinstallation rules and package ownership on a disposable copy; do not edit extension rows blindly. Restore the old article block and Sourcerer states if needed. Never restore the unsafe menu 101 value.

Still required: real package installation over 0.2.0, repeated upgrade and package update discovery, failure/rollback and uninstall tests; real JCE/TinyMCE insertion and saving by Manager; frontend/cache/Daily Content regression; actual provider playback. Available local Joomla trees/processes do not establish isolation or separate databases. The working site is outside this development step.

References examined:
- https://manual.joomla.org/docs/next/building-extensions/install-update/installation/package/
- https://www.joomlacontenteditor.net/support/faq/editor/editor-xtd-buttons-missing
- https://developers.google.com/youtube/player_parameters
- Local Joomla `PluginAdapter.php`, `PackageAdapter.php`, button layout and `editor-api.js`; local JCE 2.9.99.10 `XTDButtonsTrait.php` and `editor.module.js` (read-only).
