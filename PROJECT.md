# Post Update Email Notifier: project record

## Authority and baseline

Canonical Git checkout: `Post Update Email Notifier/hackrepair-post-update-email-notifier`. Remote: `tvcnet/hackrepair-post-update-email-notifier`, branch main. Keep the repository under tvcnet (user decision October 19, 2026). Original working tree had local 1.4.4 changes over commit b763123 (1.4.3); preserve those changes. Backup: outer `backups/2026-10-19-baseline/source-before-standardization.tar.gz`. Historical ZIPs, source artwork and outer notes remain untouched.

The latest 1.4.4 ZIP matched all plugin source bytes; its .git/index differed. It included the Git repository and development metadata. This is the initial packaging defect, independent of the later code refactor. Missing PUE_Notifier bootstrap was conditional, so no container class actually ran. Active services were loaded through the autoloader and global compatibility functions. A duplicated settings renderer and duplicate helper fallbacks obscured the execution path.

## Current version

1.5.0, approved for GitHub publication on October 19, 2026. Bootstrap constants/autoloader plus explicit focused includes. Preserve installed folder, PHP entrypoint, `pue_*` option keys, meta key and developer hook names. Replace Google Drive update discovery with vetted GitHub release assets. No repository transfer. No Drive manifest publication is implied. The user approved packaging and full GitHub publication after reviewing the build.

## Source map

- `post-update-email-notifier.php`: metadata, constants, loader and updater startup.
- `includes/admin.php`, `settings.php`, `admin-handlers.php`: settings-page registration, assets, compatibility handlers and grouping.
- `includes/editor.php`: Classic/Block skip UI, capability/nonce checks and one-update opt-in behavior.
- `includes/notifications.php`, `class-pue-notification-service.php`: update hook, exclusions, one-recipient sends and logging.
- `includes/class-pue-settings-manager.php`: registered settings and sanitization.
- `includes/class-pue-admin-interface.php`: current settings renderer and authenticated AJAX actions.
- `includes/helpers.php`, `sanitizers.php`, `logging.php`, composer/placeholder/logger classes: mail construction, compatibility wrappers, legacy log handling.
- `includes/class-pue-updater.php`, `class-pue-update-notices.php`, `companion-plugins.php`: GitHub-only release discovery, plain-language errors, native details and family guide.
- `plugin-update-checker/`: complete vendored library and required nested vendor dependencies; never reformat it.
- `assets/`: local styles/scripts/branding/icons. `docs/`: review, plan, history. `tests/`: focused regressions. `scripts/`: deterministic builder. `releases/`: source release notes. `dist/`: ignored ZIPs/checksums.

## Evidence and limits

Update this record from actual command results, not intentions. PHPCompatibilityWP checks the declared 7.4 floor; local CLI syntax does not establish an actual PHP 7.4 runtime result. No full external-delivery claim, large-recipient benchmark or production assessment follows from Mailpit/static checks. Existing date/notice/log storage uses legacy site-local timestamps; a timestamp migration is separate work. Bulk sending remains synchronous and capped at 200 tests; selecting many real users can still stress a request. SMTP detection is a heuristic. Do not run a bulk test against external recipients during development.

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
