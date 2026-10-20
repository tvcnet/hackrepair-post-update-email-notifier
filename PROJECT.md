# Post Update Email Notifier: project record

## Authority and baseline

Canonical Git checkout: `Post Update Email Notifier/hackrepair-post-update-email-notifier`. Remote: `tvcnet/hackrepair-post-update-email-notifier`, branch main. Keep the repository under tvcnet (user decision October 19, 2026). Original working tree had local 1.4.4 changes over commit b763123 (1.4.3); preserve those changes. Backup: outer `backups/2026-10-19-baseline/source-before-standardization.tar.gz`. Historical ZIPs, source artwork and outer notes remain untouched.

The latest 1.4.4 ZIP matched all plugin source bytes; its .git/index differed. It included the Git repository and development metadata. This is the initial packaging defect, independent of the later code refactor. Missing PUE_Notifier bootstrap was conditional, so no container class actually ran. Active services were loaded through the autoloader and global compatibility functions. A duplicated settings renderer and duplicate helper fallbacks obscured the execution path.

## Attribution and naming policy

Do not add third-party AI-assistant or provider names, branding, avatars, profile links, session URLs or credits to plugin code, public documentation, release notes, assets, branch names or commit metadata. Use the human maintainer’s configured Git identity. Do not automatically add AI co-author, generator or session trailers. Preserve legally required third-party dependency license notices. Changes to published Git history or tags require explicit approval and a recoverable backup.

## Current version

1.6.3 is the approved release being prepared to remove the Screenshots tab from View Details. The user explicitly authorized publication of 1.6.3 followed by deletion of the 1.6.2 GitHub release and tag. Older local ZIPs and recoverable Git backups will be preserved. The attribution-history rewrite remains unapproved and is separate from this release/tag deletion. Verification and withdrawal results will be recorded below.

## Source map

- `post-update-email-notifier.php`: metadata, constants, loader and updater startup.
- `includes/admin.php`, `settings.php`, `admin-handlers.php`: settings-page registration, assets, compatibility handlers and grouping.
- `includes/editor.php`: Classic/Block skip UI, capability/nonce checks and one-update opt-in behavior.
- `includes/notifications.php`, `class-pue-notification-service.php`: update hook, exclusions, one-recipient sends and logging.
- `includes/class-pue-settings-manager.php`: registered settings and sanitization.
- `includes/class-pue-admin-interface.php`: current settings renderer and authenticated AJAX actions.
- `includes/helpers.php`, `sanitizers.php`, `logging.php`, composer/placeholder/logger classes: mail construction, compatibility wrappers, legacy log handling.
- `includes/class-pue-updater.php`, `class-pue-update-notices.php`, `companion-plugins.php`: GitHub-only release discovery, plain-language errors, native details and family guide.
- `includes/class-pue-defaults.php`: single source of option defaults. `class-pue-delivery.php`: per-recipient sends, plaintext part, WP-Cron background batches. `class-pue-admin-actions.php`: every settings action for admin-post (no JS, redirect-after-POST) and admin-ajax. `class-pue-site-health.php`, `class-pue-cli.php`: Site Health tests and `wp pue` commands.
- `tests/integration/run.php`: real-WordPress checks via `wp eval-file`; mail captured with pre_wp_mail, fixture deleted and options restored.
- `plugin-update-checker/`: complete vendored library and required nested vendor dependencies; never reformat it.
- `includes/class-pue-help.php` and `assets/js/help.js`: translated native help disclosures with hover/focus/tap enhancement.
- `assets/`: local styles/scripts/branding/icons. `docs/`: review, plan, history. `tests/`: focused regressions. `scripts/`: deterministic builder. `releases/`: source release notes. `dist/`: ignored ZIPs/checksums.

## Evidence and limits

Update this record from actual command results, not intentions. PHPCompatibilityWP checks the declared 7.4 floor; local CLI syntax does not establish an actual PHP 7.4 runtime result. No full external-delivery claim, large-recipient benchmark or production assessment follows from Mailpit/static checks. Existing date/notice/log storage uses legacy site-local timestamps; a timestamp migration is separate work. The Bulk Test control remains synchronous and capped at 200 per request. Ordinary notifications use the configured automatic/background/synchronous delivery mode; background reliability depends on cron and the documented interruption policy. SMTP detection is a heuristic. Do not run a bulk test against external recipients during development.

## Release gates

1. Baseline and review recorded.
2. Required behavior regressions, shared PHPCS and syntax checks pass.
3. ZIP has one correct root, no .git/development material, all regular files 0644, required updater files and matching source bytes.
4. Zendogs/Mailpit review verifies UI, native details, test mail and a controlled notification without altering existing posts or user accounts.
5. Reviewer approves release; commit/tag/push and GitHub upload follow. Verify published digest, real updater and installed version. Keep old packages.

Consider Codex Security review before publication; it is not automatic authorization to run a security plugin.

## Review result: October 19, 2026

Shared PHPCS passed on 21 configured first-party files. Isolated notification checks (13 assertions), updater asset-selection guards, update-message regressions and 0600-source packaging regression passed. Runtime Zendogs is WordPress 7.1 / PHP 8.2.33; This is the observed runtime, distinct from the configured CI matrix. Browser settings and native View Details render without horizontal overflow at 390px. Domain/plugin subjects and full-width HTML were captured in Mailpit. The real wp_after_insert_post path was tested with a temporary published fixture: initial creation sent nothing, explicit opt-in sent one message, subsequent update was skipped. Original options were restored and the fixture deleted. A missing-template fallback defect found by that test was corrected and covered by an additional assertion.

Before publication, this repository had no GitHub releases and the manual update-check UI correctly reported unavailable information. Published-asset verification is recorded separately in the execution evidence. The old Drive manifest is unchanged. External email delivery, large-role performance, full browser/editor matrix and WordPress integration on PHP 7.4/8.3 remains unverified. GitHub CI passed the PHP 7.4/8.3 matrix for syntax, isolated notification/updater regressions, and packaging: https://github.com/tvcnet/hackrepair-post-update-email-notifier/actions/runs/39348046849. This is distinct from WordPress integration, verified locally on PHP 8.2.33.

A stale November 2025 rebase-merge directory was preserved in the outer baseline backup and cleared with git rebase --quit. HEAD, worktree and index were preserved. The rebuild was reviewed on codex/1.5.0-standardization before the approved merge and release.

View Details review correction: rely on the updater’s existing row link to avoid duplicates; restore the original 772x250 banner from outer Assets/post-update-email-notifier.png. Its baked-in branding stays unobscured; the native heading remains available to assistive technology. No-banner dialogs retain the gradient fallback.

## Publication result

Version 1.5.0 is published under tvcnet: https://github.com/tvcnet/hackrepair-post-update-email-notifier/releases/tag/v1.5.0. ZIP digest matches GitHub. Zendogs discovered the published asset and WordPress installed it, preserving active status; all 151 installed files match the ZIP. The anonymous API quota was exhausted during verification, so discovery was verified with temporary process-only authentication. See execution evidence for the exact scope and rate-limit limitation. Main-branch CI passed run 39348638013.

## GitHub presentation

The repository landing page follows Disable Other Plugin Notices: original View Details banner above the centered title, release/requirements/CI badges, product introduction, features, setup, FAQs and maintainer/support links. The GitHub About description and topics identify the notification workflow. Full changelog details remain in readme.txt. This presentation update does not change the published 1.5.0 package.

## Community support

Questions, ideas and community help: https://github.com/tvcnet/hackrepair-post-update-email-notifier/discussions. Reproducible bugs: https://github.com/tvcnet/hackrepair-post-update-email-notifier/issues. Security reports stay private. GitHub Discussions is enabled; Reddit is no longer the support destination. Documentation and support-link updates are committed for future packages without replacing published release assets.

## 1.5.1 review candidate (October 19, 2026)

Implements docs/reviews/2026-10-19-refactor-cleanup.md items 1-4: Export Log fatal error (`gmgmdate()`) fixed with the export handled on `load-settings_page_pue-settings` before page output and PHP 8.4-safe `fputcsv()` calls; AJAX/update-check feedback now follows the response result and clears stale messages; duplicate helper, sanitizer, logging and export fallbacks delegate to the service classes (public wrappers, settings keys, meta key and hooks preserved); obsolete download-button code, unused `bulkNonce`, dead CSS and the card width cap removed. Item 5 (duplicate icon PNGs) intentionally not done: no demonstrated runtime benefit and both pairs are still referenced.

Evidence: shared PHPCS 21/21; notifications, update-notices, updater, compatibility and export-log tests and the package-permissions regression pass (PHP 8.4.11 on the Mac); `node --check` passes for both scripts. Review ZIP `dist/hackrepair-post-update-email-notifier.1.5.1.zip`, 151 files, SHA-256 `ff19b8e9ebff79bbd273fcca0394c3dc11345c5f9b266445ceb3f9791939ca30`, installed on Zendogs (prior 1.5.0 install backed up in the container at /tmp/pue-1.5.0-installed-backup.tgz). In the browser on Zendogs: heading and body left/right edges match for all nine sections at emulated widths 1624, 1100, 800 and the 507px minimum, with no horizontal overflow; a real POST of the Export Log form returned text/csv with an attachment header, no page HTML and formula-escaped fields; with fetch stubbed, test-email unsuccessful and rejected responses and update-check rejection showed errors, and a retry/success cleared them; View Details shows version 1.5.1, the original banner, a visually hidden but present heading and no overflow; a real Test Email was captured by Mailpit (19 to 20 messages). Not verified: external delivery, PHP 7.4/8.3 CI for these changes (push required), the full editor matrix, the 390px width (browser minimum was 507px). Historical snapshot: this work was subsequently committed and incorporated into 1.6.0; 1.5.1 remains unpublished.

## 1.6.0 review candidate (October 19, 2026)

Implements the top-down evaluation (docs/reviews/2026-10-19-top-down-evaluation.md) with the scope you chose: defects D1-D10, the quality items, and features F1 (WP-Cron background sending), F2 (change summary), F3 (send policy, default kept as skip; first publish vs updates only), F4 (cooldown), F5 (recipient counts), F7 (preview), F12 (WP-CLI), F13 (Site Health), F15 (translation template, JS strings). Also included: personal data exporter/eraser, `pue_settings_capability`, bulk-test daily cap. Deferred: F6 custom log table and UTC timestamp migration, F8-F11, F14.

Behavior notes: after each processed update the per-post flag is deleted (site default applies) instead of stored as 1; posts saved by earlier versions keep their stored flag until their next update. An opt-in that cannot be sent is consumed and logged with a reason. Never-saved branding now uses the displayed defaults. `$post_before` is now a real WP_Post. Brand-new inserts still never notify.

Evidence (actual command results): shared PHPCS clean on all configured files; isolated suites pass (notifications 35, update-notices 8, compatibility 54 including the notification set, export/admin-actions 7, updater guards, package permissions); `node --check` passes for both scripts; POT generated with 205 strings. Review ZIP dist/hackrepair-post-update-email-notifier.1.6.0.zip, 157 files, single root, no tests/docs/dev files, SHA-256 in the sidecar. Installed on Zendogs (prior install backed up in the container at /tmp/pue-1.5.1-installed-backup.tgz). Real-WordPress integration script: 13 of 13 checks pass (meta registration, draft/publish silent, opt-in send via wp_after_insert_post, consumption, repeat skip, real $post_before change summary, cooldown, background WP-Cron batches completing the log entry, Site Health, privacy exporter, admin-post/AJAX routing); fixture deleted, options restored, no cron leftovers. WP-CLI: help, recipients, log export, test (Mailpit captured), invalid send rejected. Ego Lite on Zendogs: settings render v1.6.0 with all sections and no PHP notices, no inline handlers, no horizontal overflow; headings and bodies aligned at 1624, 1100, 800 and 394px; live recipient count updates without saving; preview opens in a sandboxed iframe with focus moved in, Escape closes and focus returns; Branding saved with JavaScript bypassed and returned to #branding with a notice that does not repeat on reload; Test Email accepted and captured by Mailpit; CSV export via admin-post returned text/csv; update check reported GitHub 1.5.0; View Details now shows the remote version (1.5.0) rather than the installed one; Classic editor shows the checkbox checked by default with the correct recipient note.

Not verified: Block editor panel in a browser (Zendogs uses the Classic editor; editor.js syntax and localization only), a real WordPress/PHP 7.4 stack, Site Health display on Zendogs (another installed plugin removes all Site Health tests; our callback verified directly), external delivery and large real recipient lists. Sandbox side effect: the no-JS Branding check saved the existing default values explicitly. Historical snapshot before the push described below. No tag or publication exists yet.

### 1.6.0 on main: CI and post-push checks

Pushed to main by fast-forwarding the 1.6.0 development work, which also includes the 1.5.1 commit. Main CI run https://github.com/tvcnet/hackrepair-post-update-email-notifier/actions/runs/39358307587 passed: php 7.4 and 8.3 (syntax, all isolated suites, JS syntax, packaging) and wordpress 8.1 and 8.3 (wp-env, latest WordPress, 13 of 13 integration checks). The wp-env PHP 7.4 image failed to build in the first run (Debian package 404s, run 39357388401), so the WordPress job now uses 8.1/8.3; PHP 7.4 remains covered by the php job. After the push, a package built from the main checkout matched all 157 files installed on Zendogs, the integration script passed there again, and the settings page and front page loaded without PHP notices in Ego Lite. No tag or release has been created.

## 1.6.0 release-readiness fixes

Implemented the confirmed recovery and domain-check findings. Queue workers now checkpoint before each transport, fence writes with lease ownership, reload state after acquiring locks and avoid replaying uncertain sends. Independent recovery retries scheduling up to five times; operators can inspect/retry jobs, failed scheduling surfaces with logging off, and payloads expire after seven days on a later recovery run. README/readme document cron, deactivation/reactivation, Reset/Clear Log and privacy-erasure behavior. Sender checks compare literal domains (normalizing www), without public-suffix guessing or authentication claims.

Verification: shared PHPCS 26 files; notifications 35 assertions, compatibility 61 including notifications, queue recovery 52 including notifications, export/admin actions 7, update messages 8, updater/package guards and JS syntax. Real WordPress 7.1 and 6.6 on PHP 8.2.33: 14 standard and 8 fault-path integration checks each. Real 1.5.0 upgrade preserves existing data. Block editor verified on 6.6 with both policies; Zendogs Classic/settings verified, including nine aligned sections at 1440/800/394px. Disposable 6.6 fixture removed; Zendogs updated. See docs/reviews/2026-10-19-1.6.0-release-readiness.md for evidence and limits. The separate review ZIP preserves all older packages; no tag/release has been created.

### Revised 1.6.0 CI confirmation

Implementation commit `355d2ff404bfba94a0b3adcd5bcaee59dda8d53f` passed https://github.com/tvcnet/hackrepair-post-update-email-notifier/actions/runs/39370866988: standalone PHP 7.4/8.3; WordPress latest on PHP 8.1/8.3; WordPress 6.6 on PHP 8.1. All five jobs passed, including the new queue-fault integration suite. The review package and installed runtime files are unchanged by this documentation-only evidence update. Publication remains pending final review; v1.5.0 is still the latest public release.

## 1.6.1 contextual-help candidate (October 20, 2026)

Added 31 translated help topics, starting with Default for each update: both policies, the per-post choice returning to the site default, saving and recipient requirements. All settings groups and diagnostic actions now explain behavior, practical steps and relevant limits. Native details/summary controls work without the enhancement script; JavaScript adds hover/focus/tap, one-open-at-a-time, Escape/outside dismissal and viewport positioning. A narrow-screen native-fallback overflow found during review was fixed. Sender-name help also clarifies that the override requires a valid From Email. Notification logic and saved options are unchanged.

Local evidence: shared PHPCS passes 27 configured files; notification, compatibility, queue-recovery, export/admin-action, updater and update-notice regressions pass; new help suite passes seven assertions (native semantics, unique IDs, escaping, unknown-key handling and explanatory copy). All three JavaScript syntax checks and the restrictive-source packaging regression pass. Translation template regenerated.

Zendogs browser evidence: v1.6.1 active, all 31 help controls present, no details nested in labels/paragraphs/headings. Hover/focus open, Escape closes while retaining focus, Tab moves to the field, click pins, another help closes the previous one, and outside click dismisses. At 394px the enhanced panel stays inside the viewport with no horizontal overflow. Native disclosure verified with scripts disabled for page load on desktop; at 394px, blocking help.js specifically verified open/close and no overflow after the CSS fix. Browser JavaScript/network/emulation overrides restored. New help tested without saving settings, sending mail or triggering bulk actions; mail transport behavior relies on the unchanged regression-tested path and the prior 1.6.0 integration evidence.

Review ZIP: `dist/review-help/hackrepair-post-update-email-notifier.1.6.1.zip`, 159 files, one correct root, all files 0644, source bytes and complete updater dependencies verified by builder. SHA-256 `006f45d63f57fbbe3ca3fa5b05afbdb3a65609094bae93301292d9df31ca0c0f`. All 159 installed Zendogs files match the ZIP. Previous installed plugin backed up at `/tmp/pue-before-1.6.1.tgz` in the container. Older ZIPs preserved. Implementation commit `6d17806f734e491f12f5cdb5c8bc90a3982bd523` passed GitHub CI run https://github.com/tvcnet/hackrepair-post-update-email-notifier/actions/runs/39473109505: standalone PHP 7.4/8.3 and WordPress integration on PHP 8.1/latest, PHP 8.3/latest and PHP 8.1/6.6. All five jobs passed, including queue-fault integration. Existing runner/action deprecation notices are non-blocking maintenance items, separate from plugin behavior. No tag or public release created; public latest verified as v1.5.0.

### 1.6.1 review adjustment: Support and Reset to Defaults

Removed the help controls and unused help text for Support and Reset to Defaults at user request; 29 help topics remain. Support links, reset confirmation and reset behavior are unchanged. Updated release notes and translation template. PHP syntax, seven help assertions and shared PHPCS (27 files) pass for this adjustment. Rebuilt both the direct `dist/hackrepair-post-update-email-notifier.1.6.1.zip` and the matching `dist/review-help/` copy; preserved the previous review ZIP/checksum in `before-support-tooltip-removal/` under each directory. Revised SHA-256: `98b0dd1dfb413c7f4e7d7c56fcc0bd0ccb58423a37607c3d8bc6916801e26bdc`. The earlier five-job CI evidence above applies to the prior candidate; this small adjustment received the stated local checks. No public release created.

### 1.6.1 review adjustment: remove Update status

Removed the settings-page Update status container, sidebar entry, help topic, manual-check JavaScript, localized feedback strings, AJAX hook/wrapper/handler and unused global checker reference. The page no longer reads or writes its status transient. Retained the legacy transient deletion in uninstall so old installations can still clean up that data. No dedicated CSS rules existed for this section; shared card/action styles remain in use. Native WordPress/GitHub release discovery, update notices, rate-limit guidance and View Details remain intact. There are now 28 help topics. Release notes, readme and translation template updated.

Checks for this revision: seven help assertions, updater release-asset guards, eight update-notice assertions, seven export/admin-action assertions, JavaScript syntax and shared PHPCS (27 files) pass. Builder verifies the correct root, 0644 permissions, complete updater dependencies and source bytes. Updated both direct dist and review-help ZIP/checksum copies; previous versions preserved in before-update-status-removal subdirectories. SHA-256: `e1d3827c1f764a332fe2c16af3b00d9317fc2768b3559b19bd008570c74c6847`. All 159 installed Zendogs files match the revised ZIP; prior installation backed up at `/tmp/pue-before-update-status-removal.tgz`. Prior full CI evidence applies to its recorded commit; this revision has the checks listed here. Publication remains separate.

Zendogs browser verification for this adjustment: v1.6.1 renders eight sections; Update status heading, sidebar link and action button are absent; all 28 remaining help controls render, with no Support/Reset help or horizontal overflow. No settings were saved and no mail/update-check action was triggered.

### 1.6.1 review adjustment: Recent Email Log help

Removed the section-level Recent Email Log and Clear Log help controls and their unused translated text. Kept the help control immediately after Export CSV. There are now 26 help topics overall. Updated readme/release wording and regenerated the translation template. Shared PHPCS (27 files), PHP syntax, seven help assertions and seven export/admin-action assertions pass. On Zendogs, invoking the installed Recent Email Log renderer confirms its only help key is `pue_export_log`; no log entries were deleted or settings saved. All 159 installed files match the revised ZIP.

Direct dist and review-help copies rebuilt at version 1.6.1; SHA-256 `200cc7d62a13abfc3c3dc194dc5c0b92abdcb887ca421ac01d0bba75515d1c97`. Prior ZIP/checksum copies preserved under before-log-tooltip-trim; prior installed plugin backed up at `/tmp/pue-before-log-tooltip-trim.tgz`. Publication remains separate.

### 1.6.1 review adjustment: clearer send-policy help

Rewrote Default for each update help in plain language: recommended emails-off behavior, the exact editor checkbox to uncheck when sending, the alternative send-by-default option, one-update editor choices, recipient selection and Save Notifications. Other sending rules still apply. Removed the longer historical-migration explanation from this tooltip; notification behavior is unchanged. Updated the existing help-copy assertion and regenerated translations. Seven help assertions and shared PHPCS (27 files) pass.

Rebuilt direct dist and review-help ZIP/checksum copies; SHA-256 `6fdc6f96014279f7240913cb679c385261259fcfba70bf5727c0ff1e21145a13`. Previous review copies preserved under before-send-policy-copy-simplification. Version remains 1.6.1 and publication remains separate.

### 1.6.1 review adjustment: post-editor checkbox illustration

Added a compact illustration to the right of Default for each update: a checked box, an arrow, an unchecked box and pointer, the exact editor label, and “Uncheck to send this update.” It uses HTML/CSS and a small decorative inline SVG, with an accessible image name describing the editor control. It contains no input or action. Existing dropdown values, option names and submission behavior are preserved; no JavaScript or image downloads are needed. Updated readme/release notes and translations.

Zendogs browser verification at 1624px: illustration sits 19px to the right of the dropdown, aligned at the top, with no horizontal overflow. At 394px it stacks below and remains fully visible without horizontal overflow. Screenshots visually reviewed at both widths. Exactly one policy select remains, in pue-form-notifications; the saved skip policy is unchanged. No settings saved or email sent. Shared PHPCS (27 files), PHP syntax and seven help assertions pass.

Rebuilt the 1.6.1 package in direct dist and review-help; SHA-256 `5a208cabbfbc725e98a388839404e24d1f9a5d110b87d6a156e1e14ab2bdc73e`. All 159 installed Zendogs files match the ZIP. Previous review copies preserved under before-policy-graphic and prior installation backed up at `/tmp/pue-before-policy-graphic.tgz`. Publication remains separate.

### 1.6.1 review adjustment: prevent stale illustration CSS

The reported bare checkbox illustration matches the old admin stylesheet being reused while review installs retain version 1.6.1. The admin stylesheet now includes its file modification time in the version query, alongside PUE_VERSION, with the existing version as fallback if the file is unavailable. This changes the asset URL without changing settings or the plugin version. Readme and release notes updated.

Zendogs browser verification using normal navigation, without cache disabling or a forced refresh: CSS URL uses `ver=1.6.1.1792497160`; computed layout is flex, the example border is solid and checkbox width is 18px. The styled dropdown/example row screenshot was visually checked. No settings saved or recipient selections changed. PHP syntax and shared PHPCS (27 files) pass. Updated direct dist and review-help ZIP/checksum copies, SHA-256 `58a83c3fa74d097b53eb0ded880ef3622ab092c1c780381b451addc9d809fa92`; all 159 installed Zendogs files match. Previous packages preserved under before-admin-css-cache-fix and prior installation backed up at `/tmp/pue-before-admin-css-cache-fix.tgz`. No public release created.

### 1.6.1 review adjustment: right-align the checkbox illustration

Aligned the illustration’s right edge with the existing “keeps your team…” note using an automatic left margin and the same 12px right inset. At 900px and below, the illustration keeps its stacked, left-aligned layout. This is a CSS-only UI change. Zendogs browser measurement at 1624px confirms both right edges are exactly 1566px; at 394px the illustration remains below the dropdown, within the viewport and without horizontal overflow. No settings changed.

Rebuilt direct dist and review-help packages at 1.6.1, SHA-256 `ec22f9b50e809ccefd1f42e0e61d108eaba6b108d1453f86c4063d6e502be7f2`. All 159 installed Zendogs files match. Prior review ZIPs/checksums preserved under before-policy-right-alignment and prior installation backed up at `/tmp/pue-before-policy-right-alignment.tgz`. No public release created.

### 1.6.1 review adjustment: remove repeated settings descriptions

Reviewed all 26 remaining help topics against visible explanations. Removed repeated text for the notification default, publish rules, cooldown, delivery mode, message summary, post types, updating-user exclusion, logging, retention, header color, sender identity and test/preview actions. Shortened the plus-alias and per-message logging checkbox labels. Removed the repeated role/post-type tip from the intro note, preserving the note itself and the editor illustration. Removed the now-unused muted-text CSS rule and unused select-row description parameter. Checkbox labels for Enable Logging and Exclude Updating User explicitly target their input IDs.

Preserved unique/action-critical information: the full supported-placeholder reference, live recipient count, real-mail transport feedback, the bulk real-email warning and remaining daily allowance, reset warning and confirmation, existing log controls and the Export CSV tooltip. Expanded logging help to cover stored addresses, WordPress personal data export/erasure, retention and CSV export. Added the specific message-summary details and sender-name placeholders to help before removing their inline equivalents.

Validation: PHP syntax, seven help assertions, seven export/admin-action assertions and shared PHPCS on 27 files pass. Zendogs browser verifies all 26 help controls remain, both checkboxes have associated field labels, no duplicate text remains in the logging cell, all select options and the placeholder list remain, sender descriptions are removed and bulk/reset warnings remain. Logging help opens on keyboard focus with the privacy/CSV content; Escape closes it. Screenshot visually reviewed. No horizontal overflow at 1624px or 394px; illustration and intro-note right edges still match. No settings saved, logs deleted or mail sent.

Updated both direct dist and review-help 1.6.1 packages; SHA-256 `b84fa490b44ddfd615b1d21e28b26ca25f4287de3e43ddd6f8318447bfcfc4d8`. All 159 installed Zendogs files match the ZIP. Previous review packages preserved under before-description-cleanup, and previous installation backed up at `/tmp/pue-before-description-cleanup.tgz`. Readme, release notes and translations updated. No public release created.

## Version 1.6.2 published (October 20, 2026)

Published release: https://github.com/tvcnet/hackrepair-post-update-email-notifier/releases/tag/v1.6.2. Tag points to `547daf2fbf71fb288a17cb00c089d22021e8baef`. Both five-job CI runs pass: main run 39480818075 and tag run 39481297098. Local shared PHPCS covers 27 files; all 69 packaged PHP files lint clean; behavior, JavaScript and permission regressions pass.

Direct dist package: `dist/hackrepair-post-update-email-notifier.1.6.2.zip`, 159 files, 521934 bytes, SHA-256 `869f6e3afc7d7260b4e54e48cae7fe40b8c8d3b1f343bf71671d2350affc8996`. Published asset digest, downloaded ZIP, local ZIP and sidecar agree. Older packages and public v1.5.0 preserved.

Zendogs’ installed update checker discovered the exact public release anonymously. WordPress Plugin_Upgrader installed it; its standalone manual-update path deactivated the plugin until the caller completed activation, so activation was completed through WP-CLI. Final status active, version 1.6.2; all 159 installed files match GitHub. Saved plugin settings/logs compared unchanged during upgrade. Prior installation backed up at `/tmp/pue-before-published-1.6.2.tgz`.

After installation, 14 standard and 8 queue-fault real-WordPress checks pass; options restored, fixtures removed and no pending jobs remain. Mailpit captured one actual release-verification email (22 to 23 messages) with the domain/plugin subject and full-width HTML, without adding a log entry. Browser confirms the compact settings, 26 help controls, no Update status panel, active plugin row, native View Details version 1.6.2, banner and Other THRG Plugins tab. Detailed evidence and limits are in the verification record.

## Branch cleanup (October 20, 2026)

At the user’s explicit request, deleted every branch except main: 5 GitHub branches and 3 local branches. Only main remains locally and on origin; origin/HEAD is its normal symbolic alias. No commit history was rewritten and the v1.5.0/v1.6.2 release tags and published ZIPs are unchanged. A complete, verified Git bundle and the original ref inventory are preserved outside the distributable repository at `/Volumes/BACKUP8TB2/plugins/Post Update Email Notifier/backups/2026-10-20-branch-cleanup-3v_4bmua`.

Current tracked files are free of the previously unwanted provider/account references. The attribution policy is recorded above and in AGENTS.md. Four historical commits still contain AI co-author/session trailers; removing those from published history would change descendant commit IDs and the v1.6.2 tag, and requires explicit approval under the published-tag protection rule. No such rewrite has been performed.
