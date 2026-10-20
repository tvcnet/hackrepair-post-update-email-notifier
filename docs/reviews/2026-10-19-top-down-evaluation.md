# Top-down evaluation and feature planning: October 19, 2026

Review and planning notes only. No code, assets, settings, installed sites or releases were changed. Basis: full read of all first-party PHP, JavaScript, CSS, tests, CI, readme and project docs in the working tree (1.5.1 review candidate, uncommitted). Findings marked **confirmed in code** were established by reading the source paths named; none of the new findings below were reproduced at runtime. Each item needs a regression test before it is fixed.

## 1. Overall assessment

The plugin is small (about 2,600 lines of first-party PHP/JS) and does one job. Security fundamentals are sound: every admin action checks a capability and a nonce, output is escaped, the message template goes through `wp_kses_post()`, From Name strips CR/LF, CSV export is formula-escaped, and the GitHub updater accepts only a validated release asset. The 1.5.x work fixed packaging, the export crash and misleading feedback.

The main weaknesses are structural and come from its history:

- **Three save mechanisms** (options.php, AJAX, page-render POST handlers), and the non-JavaScript path is broken for two sections.
- **The same default strings duplicated** in about eight places, and they disagree.
- **Synchronous sending** inside the editor's save request.
- **Log storage in one option array** that also feeds a misleading health warning.
- **Copy-paste leftovers** from sibling plugins in user-facing text.

None is a security emergency. Several are user-visible correctness bugs worth a 1.5.2 or 1.6.0.

## 2. Correctness defects (fix first)

| # | Finding | Where | Effect |
|---|---|---|---|
| D1 | Update-check error text names the wrong plugin: "Disable Other Plugin Notices could not reach its update service… notice grouping." | includes/class-pue-update-notices.php:87-91 | Confirmed in code. Users see another product's name on a failed manual update check. Strings are also untranslatable. |
| D2 | View Details always reports the installed version. PUC injects remote info at `plugins_api` priority 20; `pue_companion_plugin_info()` runs at 100 and overwrites `$result->version` with `PUE_VERSION`. | includes/companion-plugins.php:24 | Confirmed in code. When an update exists, the details dialog shows the old version number. Only set the version in the local fallback (`pue_local_plugin_info`). |
| D3 | `$post_before` is always null. `pue_notify_after_insert()` registers 3 args and passes `null`, although `wp_after_insert_post` supplies `$post_before` (WP 5.6+). | includes/notifications.php:13-22 | Confirmed in code. The documented filters (`pue_should_notify`, `pue_placeholders`, `pue_email_*`, `pue_email_sent`) promise a `WP_Post $post_before` and never get one; blocks any "what changed" feature. |
| D4 | False "Email delivery appears to be failing" notice. The 24-hour failure count treats every `sent = 0` entry as a failure, including intentional "Not sent" skips. | includes/helpers.php:366-372 | Confirmed in code. With logging on, three ordinary edits of posts left on the default "don't send" produce an SMTP warning. Skips must be excluded. |
| D5 | Branding and Email Identity cannot be saved without JavaScript (or if admin.js fails). Both forms post back to the settings page, but no handler processes `pue_save_branding_nonce` / `pue_save_identity_nonce` there, and they do not use `settings_fields()`. | class-pue-admin-interface.php:225, 259 | Confirmed in code. The submit silently does nothing. The earlier cleanup review assumed non-JS paths worked; that holds only for Notifications (options.php). |
| D6 | Opt-in consumed without sending. The per-post filter (priority 5) resets `_pue_do_not_notify` to 1 before the post-type and role checks run. | includes/editor.php:149-151 vs class-pue-notification-service.php:28-36 | Confirmed in code. If the type is excluded or no roles are set, the editor's explicit "send" choice is spent with no email and no log. Reset only after a send attempt. |
| D7 | Plaintext AltBody leaks. The composer stores the AltBody in `$GLOBALS['pue_altbody']`; the first later `phpmailer_init` consumes it. | class-pue-email-composer.php:58-67, helpers.php:39-46 | Confirmed in code, only when `pue_email_plaintext_enabled` is filtered on. Only the first recipient gets the plaintext part, and the skip-logging path composes without sending, so an unrelated next email can receive this plugin's text. The SMTP probe also fires `phpmailer_init` and can swallow it. |
| D8 | Duplicate failure log rows. `capture_mail_failure()` logs one row per failed `wp_mail()`, and the caller also logs a summary row. | class-pue-logger.php:57-101 | Confirmed in code. Failures are double-counted in the log and in D4's notice. |
| D9 | Inconsistent defaults. The header and footer defaults in the email template ("Post Update Notification", "Thank you for supporting us.") differ from the registered and displayed defaults ("[site_name] Notification", "Thank you for supporting [site_name]"). Subject and message defaults are repeated in 7+ places, some translated and some not. | helpers.php:389-391, class-pue-settings-manager.php, admin interface, editor.php | Confirmed in code. A site that never saved Branding sends different text than the settings screen shows. |
| D10 | Page-render POST handlers without redirect (Test Email, Clear Log, Reset). Reloading the page re-submits; the Test Email non-JS path re-sends mail. Notices are echoed before `.wrap`. | class-pue-admin-interface.php:35-104 | Confirmed in code. Move to `load-settings_page_pue-settings` or `admin_post_*` with POST-redirect-GET. |

Smaller correctness notes:

- **Uninstall leftovers:** uninstall leaves `_pue_do_not_notify` post meta and the `pue_github_limit` transient.
- **Untranslated strings:** the block editor label is not translatable even though `wp-i18n` is a dependency, and `languages/` has no `.pot`.
- **Stale copy:** the `@since 2.0.0` in class-pue-updater.php is copied from another plugin.
- **Readme claims that don't match the code:** an "SMTP Active status chip" that no longer exists, and "otherwise all types do", although the editor control and meta exist only for public types.

## 3. Code quality

**Architecture.** The 1.5.0 split into service singletons plus compatibility wrappers is reasonable for preserving public function names. What remains:

- **Vestigial guards:** most `class_exists()` and `function_exists()` checks around internal classes are left over from the old non-class build and can go once the wrappers are stable.
- **A defaults source:** introduce one source for defaults (for example `PUE_Defaults`) and use it for `register_setting`, rendering, sending, reset and uninstall. This fixes D9 and removes about 40 duplicated lines.
- **One save mechanism:** pick one save path. Either use the Settings API with `settings_fields()` per group (works without JS, and AJAX can post to the same endpoint), or a single `admin_post` handler with redirect. Drop the extra custom nonce on the Notifications form, which options.php ignores.
- **Inline code in templates:** `render_settings_page()` (~380 lines) mixes request handling, data loading and markup. Split it into action handlers plus per-section partials. Inline styles and the inline `onclick="confirm()"` should move to CSS/JS (CSP-friendlier).

**Sending and performance.**

- **Recipient lookup:** `get_users()` per role loads full user objects. Use one query with `role__in` and `fields => user_email`.
- **Synchronous sending:** all mail goes out inside the editor's save request. With large roles or slow SMTP, the save can time out or block the editor. This is already documented as a limit; see F1 for the fix.
- **Settings page cost:** every settings page view loads the whole log option (up to 1,000 entries with full recipient lists) and runs the SMTP probe. The probe fires `phpmailer_init` for all plugins (side effects with mail-logging plugins; see D7). Cache the probe result briefly or run it only on demand.
- **Log storage:** the whole log is rewritten on every event. Concurrent sends can lose entries, and storing every recipient address can grow the option to megabytes. See F6.

**Time handling.** The legacy `current_time('timestamp')` site-local storage is a documented, deliberate exception. Plan the migration to UTC `time()` plus `wp_date()` display as one dedicated change, with a stored-format version marker.

**Frontend.**

- **admin.js** is now coherent after 1.5.1. Remaining issues:
  - The error modal does not move focus into the dialog or restore it on close, and has no focus trap.
  - User-facing JS strings are hard-coded English; pass them via `wp_localize_script` or `wp.i18n`.
  - The scroll and "done" `sessionStorage` helpers are fine (wrapped in try/catch).
- **editor.js:** wire `wp.i18n.__` into it, and consider hiding it on post types where the meta is not registered.

**Testing and CI.** The isolated stub tests are well targeted, but gaps remain:

- **Tests missing from CI:** CI does not run `tests/compatibility.php` or `tests/export-log.php` (added in 1.5.1).
- **No standards or JS checks in CI:** there is no PHPCS step and no JS lint. PHPCS is shared and local by policy, so a CI step would need a pinned standard via Composer. That is a policy decision for you.
- **No WordPress integration tests:** there is no real-WordPress test. A small wp-env or WP test-suite job (WP 6.6 and latest, PHP 7.4 and 8.3) would cover the hook path, meta defaults, Settings API saves and uninstall, which the stubs cannot.
- **Tests for the defects above:** D3-D8 each need a failing test first.

**Security posture (no critical findings).**

- **Bulk Test:** an administrator can send up to 200 messages per request to any external address, with no daily cap. Admin-only, but consider a per-day cap and a confirmation step.
- **Personal data in logs:** logs keep recipient addresses in `wp_options`, which end up in every database backup. Add personal-data exporter/eraser hooks, a "store counts only" option, and age-based retention.
- **Capability:** the settings capability is hard-coded to `manage_options`. Consider a filter for agencies that delegate.
- **Codex Security review:** a separate one remains outstanding per the process docs. It was not run.

## 4. Feature candidates

Ranked by value to site owners versus effort. These are planning notes, not commitments.

| ID | Feature | Value | Effort | Notes |
|---|---|---|---|---|
| F1 | Background sending (WP-Cron / Action Scheduler batches) with progress in the log | High | M | Removes the editor-save timeout risk; needed before large roles are safe. Keep synchronous as an option for tiny sites. |
| F2 | "What changed" summary in the email (title change, excerpt, word-count delta, link to the revision diff) | High | M | Depends on D3. Placeholders such as `[change_summary]`, `[revision_url]`. |
| F3 | Send policy setting: "skip by default" (current) vs "send by default", plus "notify on first publish" and scheduled-publish handling | High | S-M | The changelog history shows confusion over defaults; make it an explicit site choice. Distinguish draft→publish from update. |
| F4 | Cooldown/debounce per post (for example one email per post per N minutes) | High | S | Prevents a burst of emails from repeated saves. |
| F5 | Recipient preview: show the resolved recipient count in Settings and in the editor checkbox ("Will notify 14 people") | Medium-High | S | Cheap, and reduces surprise sends. |
| F6 | Log storage in a custom table with date-based retention, filters and pagination; privacy exporter/eraser; optional counts-only mode | Medium | M | Fixes the option-size and concurrency limits; enables a better log UI. |
| F7 | In-admin HTML preview of the template without sending | Medium | S | Faster than sending a test email; reuses the composer. |
| F8 | User-level opt-out (profile checkbox) and individual-user or extra-address recipients beyond roles | Medium | M | Common request for editorial teams. |
| F9 | Routing rules: per post type or category → roles | Medium | M | Builds on F8. |
| F10 | Daily/weekly digest mode | Medium | M | Needs F1 + F6. |
| F11 | Additional channels: generic webhook, Slack/Teams incoming webhooks | Medium | M | Same event, different transport; keep email first. |
| F12 | WP-CLI commands: `wp pue test`, `wp pue send <post>`, `wp pue log export` | Low-Medium | S | Helps support and the Zendogs test workflow. |
| F13 | Site Health tests (mail transport, From-domain alignment, recent failures) replacing the custom SMTP notice | Low-Medium | S | Native placement; resolves D4 by design. |
| F14 | Multisite network defaults | Low | M | Only if customers ask. |
| F15 | Full i18n: `.pot` generation in the build, JS strings via `wp.i18n` | Low-Medium | S | Pairs with D1. |

Deliberately not recommended: bundling SMTP credentials or a mail transport (the existing advice to use a dedicated SMTP plugin is right); Bcc delivery (one email per recipient is the safer privacy default).

## 5. Suggested sequencing

1. **Publish 1.5.1** as already planned, after your approval. This review does not change that build.
2. **1.5.2 (small, correctness):**
   - D1, D2, D4, D6, D8 with regressions.
   - A `PUE_Defaults` source (D9).
   - Add compatibility/export-log tests to CI.
   - Fix uninstall leftovers and readme accuracy.
3. **1.6.0 (structure):**
   - One save mechanism with POST-redirect-GET and a working non-JS path (D5, D10).
   - D3 `$post_before` (documented filter behavior changes from null to `WP_Post`; note it in the changelog).
   - D7 AltBody handling.
   - Efficient recipient query.
   - Modal focus handling and JS i18n.
   - A WordPress integration-test job.
4. **1.7.0 (features):** F3, F4, F5, F7, then F1 and F2.
5. **Later:** F6 with the UTC timestamp migration, then F8-F15 as demand shows.

## 6. Decisions needed from you

- Whether CI should run PHPCS with a pinned standard, or PHPCS stays shared and local only.
- The default send policy for F3 (keep "skip by default" as the shipped default?).
- Whether log privacy (counts-only, age-based retention) should become the default for new installs.
- Whether to schedule the separate Codex Security review before 1.6.0.
- Background-sending dependency: WP-Cron only, or Action Scheduler (heavier, but more reliable on busy sites).

## Execution status (October 19, 2026)

Implemented locally as 1.6.0 on branch claude/1.6.0 with your scope decisions (local only; fixes, structure and core features F1-F5, F7, F12, F13, F15; skip-by-default kept; WP-Cron for background sending). D6 was implemented as "consume and log the reason" rather than "keep the opt-in pending", so a stale opt-in cannot fire months later. Evidence and remaining limits are recorded in PROJECT.md under "1.6.0 review candidate". Deferred: F6 with the UTC timestamp migration, F8-F11, F14, and the Codex Security review.
