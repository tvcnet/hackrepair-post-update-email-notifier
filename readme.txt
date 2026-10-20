=== The Hack Repair Guy's Post Update Email Notifier ===

Contributors: hackrepair, tvcnet
Tags: email notifications, post update, roles, html email, admin
Donate link: https://hackrepair.com/donations/buy-jim-a-coffee
Support link: https://github.com/tvcnet/hackrepair-post-update-email-notifier/discussions
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Send branded HTML emails to selected user roles when you announce an update to published content. Choose who is notified, whether editors opt in or opt out for each update, and how the email looks. Preview the email without sending, send yourself a test, and review the results in an optional log.

= Features =
- Role-based recipients, with a live count of who will be emailed and an option to exclude the updating user
- Per-post “Don’t send email notifications” checkbox in the Classic and Block editors, showing how many people the update will email
- Site-wide default: editors opt in for each update (recommended) or opt out
- Notify on first publish (including scheduled posts) and later updates, or on updates only
- Per-post cooldown so repeated saves do not send a burst of emails
- Background sending in small WP-Cron batches for larger lists, so saving a post stays fast
- “What changed” summary placeholder: title changes, words added or removed, excerpt updates, plus a link to the revision comparison
- Branded HTML wrapper with customizable header text/color and footer (supports placeholders, incl. [year])
- Preview the email in the settings page without sending, or send a test to yourself
- Email Identity options for From/Reply-To (no SMTP credentials)
- Optional log (50/200/1000 entries) with CSV export, failure details, skip reasons and background progress
- Site Health checks for repeated delivery failures, sender-domain alignment and background sending
- WordPress personal data export and erasure for addresses stored in the log
- WP-CLI commands: `wp pue test`, `wp pue send`, `wp pue recipients`, `wp pue log`
- Works with or without JavaScript; translation-ready (POT included)
- Developer hooks to customize placeholders, recipients, subject, headers, change summaries and final HTML

= How it works =
- On `wp_after_insert_post`, if the post is published, the plugin checks the per-post choice, the site default, the post type, the selected roles and the cooldown, then emails each recipient individually.
- Autosaves, revisions and brand-new programmatic inserts are skipped.
- If you select post types in Settings, only those types trigger notifications; otherwise, all types can.
- After an update is processed, the post’s checkbox returns to the site default.
- When an editor opted in but nothing could be sent (no roles, excluded post type, no recipients, cooldown), the log records why.
- Lists larger than 20 recipients are sent in the background in Automatic delivery mode (filter `pue_background_threshold`).
- The Test Email button sends the saved template to your user email (falls back to the site admin email).

= Admin UX =
- Section saves run over AJAX with inline feedback; without JavaScript the same forms save and return with a notice
- Clear Log, Reset and CSV export work through standard form posts

= Template placeholders =
- `[post_title]` — The post/page title
- `[post_url]` — The public permalink to the post/page
- `[editor_name]` — The display name of the user performing the update (the author for scheduled posts)
- `[author_name]` — The post author’s display name
- `[post_type]` — The post type (singular label)
- `[site_name]` — Your site’s name
- `[updated_at]` — Update timestamp using your site’s date/time formats
- `[post_edit_url]` — Admin edit URL for the post
- `[year]` — Current 4-digit year
- `[event]` — “published” or “updated”
- `[previous_title]` — The title before this save
- `[change_summary]` — A short list of what changed
- `[revision_url]` — The latest revision comparison

= Developers =
Filters/actions available:
- `pue_should_notify( bool $should, int $post_ID, WP_Post $post_after, WP_Post|null $post_before )`
- `pue_placeholders( array $maps, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor )` (`$maps = ['subject'=>[], 'message'=>[], 'brand'=>[]]`)
- `pue_email_subject( string $subject, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor, array $subject_map )`
- `pue_email_message( string $message, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor, array $message_map )`
- `pue_email_recipients( array $recipients, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor )`
- `pue_email_headers( array $headers, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor )`
- `pue_email_template_html( string $html, string $message, int $post_ID, WP_Post $post_after, WP_Post|null $post_before, WP_User $editor )`
- `pue_email_sent( bool $sent, array $recipients, string $subject, string $message, int $post_ID, WP_Post|null $post_after, WP_Post|null $post_before, WP_User|null $editor )` (background sends pass a null `$post_before`)
- `pue_change_summary( string[] $items, int $post_ID, WP_Post $post_after, WP_Post|null $post_before )`
- `pue_background_threshold` (default 20), `pue_background_batch_size` (default 20), `pue_bulk_daily_cap` (default 1000), `pue_settings_capability` (default `manage_options`)

Since 1.6.0, `$post_before` is the post as it was before the save (WordPress 5.6+), instead of always null.

== Examples ==

= 1) Add a custom placeholder and use it =
```
add_filter( 'pue_placeholders', function( $maps, $post_ID, $post_after ) {
    $categories = get_the_category_list( ', ', '', $post_ID );
    $plain      = wp_strip_all_tags( $categories );
    $maps['subject']['[categories]'] = $plain;
    $maps['message']['[categories]'] = $categories ?: '';
    return $maps;
}, 10, 3 );
```

= 2) Exclude recipients by domain =
```
add_filter( 'pue_email_recipients', function( $emails ) {
    return array_values( array_filter( (array) $emails, function( $email ) {
        return ! str_ends_with( strtolower( $email ), '@example.com' );
    } ) );
} );
```

= 3) Add headers (e.g., Reply-To)
```
add_filter( 'pue_email_headers', function( $headers ) {
    $headers[] = 'Reply-To: qa@example.org';
    return $headers;
} );
```
Note for developers: this plugin sends one email per recipient (individual-only). Using Bcc only makes sense if you override delivery behavior via hooks/filters; by default, Bcc has no effect.

== Uninstall ==

Deleting the plugin removes its settings and data on every site of a multisite network: all `pue_*` options (settings, log, bulk-test usage, SMTP notice state), the `_pue_do_not_notify` and `_pue_last_sent` post meta, queued background jobs and their WP-Cron events, and its update-status transients.

== Settings help ==

Use the question-mark icons beside settings and actions for explanations and practical steps. Start with Default for each update to choose opt-in or opt-out behavior. Hover, focus with the keyboard, or tap to open help; press Escape or click outside to close it. Without JavaScript, the icons still work as native expandable help. Opening help never changes settings or sends email.

== Background delivery and recovery ==

Automatic mode queues lists above 20 recipients; batches require WP-Cron or a server cron job. Recovery runs every 15 minutes and retries missing scheduling up to five consecutive failures. Site Health flags scheduling problems even when logging is off. Use `wp pue jobs` to inspect status and `wp pue retry JOB_ID --yes` to schedule only unattempted recipients again.

Progress is persisted per recipient. Interrupted or uncertain sends are not automatically replayed; exactly-once delivery is not guaranteed. Pending payloads expire after seven days and are deleted on the next recovery run. Deactivation pauses callbacks; reactivation may resume unexpired work. Reset and Clear Log do not cancel queued messages. Uninstall removes queued jobs and callbacks. The privacy eraser removes addresses from log recipient lists; it does not cancel queued notifications or remove arbitrary message text.

== Support ==

For questions, ideas and community support, visit GitHub Discussions:
https://github.com/tvcnet/hackrepair-post-update-email-notifier/discussions

Report reproducible bugs through Issues:
https://github.com/tvcnet/hackrepair-post-update-email-notifier/issues

Report security concerns privately using the repository security policy:
https://github.com/tvcnet/hackrepair-post-update-email-notifier/blob/main/SECURITY.md

== Frequently Asked Questions ==

= When are emails sent? =
When a published post is saved and the update is set to send: by default an editor unchecks “Don’t send email notifications” for that update. You can switch the site default so updates send unless the editor checks the box. Autosaves and revisions are skipped.

= Who receives the emails? =
All users in the selected roles (deduplicated, case-insensitive). If enabled, the updating user is excluded. Settings and the editor show the current count.

= Does it work with custom post types? =
Yes. If you leave Post Types empty, all post types are eligible. The editor checkbox appears on public post types.

= Does it work with SMTP plugins? =
Yes. The plugin uses `wp_mail()`, so it is compatible with SMTP plugins (WP Mail SMTP, Post SMTP, FluentSMTP, etc.). The settings page notes when an SMTP transport is detected.

= How do I improve deliverability? =
Use a From address that matches your website’s domain (Site Health flags a mismatch), an SMTP plugin, and domain authentication (SPF/DKIM). Check spam/junk folders when testing.

= Can I add a plain-text alternative? =
Yes. `add_filter( 'pue_email_plaintext_enabled', '__return_true' );` adds one to every recipient’s message. Adjust the wrap width with `pue_email_plaintext_width` and the text with `pue_email_plaintext`.

= What happens with large recipient lists? =
In Automatic delivery mode, lists above 20 recipients are queued and sent in WP-Cron batches of 20, so the editor’s save returns quickly. The log shows “Queued (sent/total)” until the job completes. If WP-Cron is disabled, make sure a server cron job runs, or choose “Always send right away”.

= How do I reset to defaults? =
Go to Settings → Post Update Notifier and click “Reset to Defaults”. This restores all plugin options and clears the log (posts/users are unaffected).

= Updates =

Updates come from GitHub releases at https://github.com/tvcnet/hackrepair-post-update-email-notifier. Only a published release with a matching vX.Y.Z tag and hackrepair-post-update-email-notifier.X.Y.Z.zip asset is offered. Auto-generated source archives are rejected. View Details shows the available version and works from the bundled readme when remote information is unavailable.

Existing Google Drive installations need a one-time manual upload to move to the GitHub updater.

= What data does the plugin store? =
The settings options, the optional recent email log (autoload disabled), short-lived background job records, and two post meta keys (the per-post choice and the last send time). The log can contain recipient addresses, subjects, post titles and failure details; it is included in WordPress personal data export and erasure. Treat CSV exports as potentially private data.

= Security notes =
Admin actions require the `manage_options` capability (filterable with `pue_settings_capability`) and are protected by nonces. Inputs pass through sanitizers; mail headers are built from sanitized values and From Name strips CR/LF. Bulk tests are capped at 200 per request and 1,000 per day.

== Installation ==

= Install a GitHub review or release ZIP =
1. Download the plugin ZIP from the repository's Releases page (or the supplied local review build).
2. Go to Plugins > Add New > Upload Plugin, select the ZIP, install and activate.
3. Open Settings > Post Update Notifier. No roles are selected by default.

= Manual installation =
1. Upload the `hackrepair-post-update-email-notifier` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu

== Screenshots ==
1. Settings screen showing roles, post types, template, and options

== Changelog ==

= 1.6.1 =
* Cleanup: Removed the settings-page Update status container and its manual-check code. Use the standard WordPress Plugins screen for plugin updates.
* New: Contextual help tooltips for notification rules, templates, branding, sender settings, preview/test actions, CSV export and bulk testing.
* Accessibility: Help opens with hover, keyboard focus or tap; Escape and clicking outside close it. Native disclosures remain usable without JavaScript.
* Includes the unreleased 1.6.0 features and reliability fixes.

= 1.6.0 =
* New: Site-wide default for the per-post checkbox (opt in or opt out), first-publish vs updates-only notifications, and a per-post cooldown.
* New: Background sending in WP-Cron batches for larger recipient lists, with progress in the log.
* New: [change_summary], [revision_url], [previous_title] and [event] placeholders.
* New: Preview the email without sending; live recipient counts in Settings and the editor.
* New: Site Health checks, WP-CLI commands (`wp pue`), personal data export/erasure for the log, translation template, and a daily cap for bulk tests.
* Fixed: A failed update check named another plugin; View Details showed the installed version instead of the available one.
* Fixed: Developer filters now receive `$post_before` instead of null.
* Fixed: Skipped updates no longer trigger the “Email delivery appears to be failing” notice, and each failure is logged once with its error.
* Fixed: Branding and Email Identity now save without JavaScript; form actions redirect after saving so reloading does not resend a test email.
* Fixed: An editor’s opt-in is no longer silently lost when nothing can be sent; the log records the reason.
* Fixed: The optional plain-text part is attached to every recipient and never to unrelated email.
* Fixed: Unsaved branding now uses the same defaults the settings screen shows.
* Changed: One query loads recipient addresses; uninstall also removes post meta, queued jobs and update transients; accessible modals; no inline script handlers.

= 1.5.1 =
* Fixed: Export Log no longer fails with a fatal error, and the CSV download is sent before the settings page renders.
* Fixed: Test email, bulk test, settings save and update-check buttons now show an error when a request is rejected or fails, instead of a false "Done!", and clear stale messages on retry.
* Fixed: Section headings, card bodies and form tables on the settings page now share the same width.
* Changed: Consolidated duplicate fallback code and removed unused scripts, styles and a redundant nonce value. Public function names, settings keys and hooks are unchanged.

= 1.5.0 =
* Standardized project structure, reproducible 0644 ZIP packaging, and shared PHPCS.
* Migrated update discovery to GitHub releases in tvcnet/hackrepair-post-update-email-notifier.
* Added native View Details, THRG icons and accessible opening animation.


= 1.4.4 =
- Fix: The per‑post “Don’t send email notifications” checkbox now defaults to off (send by default) and respects later unchecking. We store an explicit value and only skip when the box is checked; one-time skips still clear after use.

= 1.4.3 =
- Change: The per‑post “Don’t send email notifications” checkbox is now checked by default for new posts (and posts with no prior setting), making it easier to pause notifications intentionally. Existing posts with an explicit setting are respected.

= 1.4.2 =
- Docs: Clarified security review points — AJAX Update Check uses `update_plugins` capability (more appropriate than `manage_options`); AJAX handlers already return JSON via `wp_send_json_*`, while `wp_die()` is used only for non‑AJAX form posts.
- Fix: The per‑post “Don’t send email notifications” checkbox is now registered for all public post types, ensuring Gutenberg saves the flag consistently (Pages and CPTs included). Skips are correctly honored.
- Enhancement: When a skip is used, the Recent Email Log now shows Status: Not sent, and CSV exports include `skipped` in the status column for those entries.

= 1.4.1 =
- Feature: Added a per‑post “Don’t send email notifications” checkbox in the editor (Classic: Publish box; Block Editor: Status & visibility). When checked, the next update for that post will not send emails; the flag auto‑clears afterward.
- Internal: Registered REST‑exposed post meta `_pue_do_not_notify` and guard via `pue_should_notify`.

= 1.4.0 =
- Architecture: start incremental OOP refactor with simple autoloader and new service classes (Logger, AdminInterface, NotificationService). Existing hooks, AJAX actions, and option names preserved.
- Settings: route registration and sanitizers via PUE_Settings_Manager (backward-compatible wrappers retained).
- Email: delegate composition and placeholder building via PUE_Email_Composer and PUE_Placeholder_Engine; public helper functions remain for back-compat.
- Delegation: route logging, CSV export, and `wp_mail_failed` capture through `PUE_Logger` (with fallback code paths for safety).
- Admin AJAX: route AJAX handlers (test email, force update check, bulk test, save sections) through `PUE_AdminInterface` without changing action names.
- Notifications: route update/test email flows through `PUE_NotificationService` while keeping identical behavior (one email per recipient; same filters/placeholders/headers).
- Fix: `pue_email_sent` now passes the composed HTML message instead of an undefined variable.

= 1.3.7 =
- Uninstall: remove all plugin options on uninstall (single‑site and multisite) to ensure a clean removal.
- Defaults: updated fallback/default message to use HTML `<br />` line breaks for consistent rendering.
- Copy: minor logging description and wording improvements in settings.

= 1.3.6 =
- Mobile-first polish: sidebar stacks under header on small screens; helper note drops below or hides on mobile.
- Accessibility: higher-contrast primary buttons; visible focus rings; reduced-motion support for spinner.

= 1.3.5 =
- Settings: Added a floating helper note next to “Select Roles to Notify” with a quick overview and a tip for pausing notifications.

= 1.3.4 =
- Settings: Update Status (Cacheless) button to force-refresh the Drive manifest without leaving the page.
- Note: The plugin-row “Check for updates now” link was removed in later versions; use the Settings button instead.

= 1.3.3 =
- Google Drive self‑updater: added on‑demand “Update Status (Cacheless)” in Settings and plugin‑row “Check for updates now”.
- Security hardening: manifest host/size/shape validation; allowlist Drive hosts for packages; optional SHA‑256 verification of ZIP (enabled when `sha256` present in manifest; optional by default).
- UX: inline status refresh (no navigation); removed download button from Settings card.
 

= 1.3.1 =
- Docs consistency: clarified header examples and individual-only delivery; removed older, confusing Bcc references from docs/changelog.
- Performance note: added guidance for large recipient lists.

= 1.3.0 =
- Individual-only delivery for notifications and testing; simplified behavior and clearer logging.
- Bulk Test (Testing Only): sends N messages individually; optional “Generate plus-aliases” to simulate many unique recipients to one inbox; optional detailed logging.
- Error details: retains the “View error” modal in Recent Email Log; summary vs detailed logging behaves consistently for bulk tests.

= 1.2.12 =
- Security hardening: centralized group save helpers used by AJAX saves to maintain sanitizer parity; From header CR/LF stripping; ensured logs option saved with autoload=no.

= 1.2.11 =
- Save buttons (Notifications, Branding, Identity): converted to AJAX with spinner/disabled state and inline “Done!”; no page reload

= 1.2.10 =
- Reset to Defaults: spinner + disabled state while processing; shows Done! after reload
- Export CSV: shows spinner + disabled state until download starts

= 1.2.9 =
- Test Email: added a small spinner + disabled state while sending; restores state after response

= 1.2.8 =
- Test Email over AJAX: no page reload; inline success/failure notice and Done! indicator
- Localized admin script with nonce + ajax_url

= 1.2.6 =
- Admin UI polish: removed duplicate inline nav script (uses enqueued JS only)
- Readme: improved deliverability guidance
- Minor cleanup and consistency

= 1.2.5 =
- Email Identity: optional From Name (supports placeholders), From Email, and Reply‑To fields
- SMTP guidance: admin notice after 3+ failures in the last 24 hours recommending an SMTP plugin
- Header/Footer: placeholders supported (including [year]) across branding fields

= 1.2.4 =
- Branding options: customizable email header text, header bar color, and footer text (supports [year])
- Default message updated to a concise “latest update” template
- Removed legacy `pue_email_template()`; all emails use the branded template function

= 1.2.3 =
- Security hardening: sanitize all recipient emails before send; validate test email address
- CSV export hardening: prevent spreadsheet formula injection by prefixing risky fields
- Minor cleanup and consistency in admin messages

= 1.2.2 =
- Test Email: fixed a UI bug where success could be shown as failure; pue_send_test_email now returns the send result so the notice reflects reality

= 1.2.1 =
- Settings: fun, colorful success/error notices for actions (Test Email, Clear Log)
- Test Email: sends to current user email (fallback to admin); clearer description and button text
- Logging: retention selector (50/200/1000), CSV export, failure details via wp_mail_failed

= 1.2.0 =
- WordPress.org readiness: added readme.txt, languages directory, and index.php
- Internationalization: text domain header, load_textdomain, translated UI strings, translatable email heading
- Settings link on the Plugins screen
- New settings: Post Types filter and Exclude Updating User
- Security: nonce + capability check for “Send Test Email” action
- Sanitization: register_setting callbacks for roles, subject, message, post types, and boolean
- More placeholders: [site_name], [post_type], [updated_at], [author_name], [post_edit_url]
- Developer hooks: pue_should_notify, pue_placeholders, pue_email_subject, pue_email_message, pue_email_recipients, pue_email_headers, pue_email_template_html, pue_email_sent
- Uninstall cleanup: removes plugin options (single + multisite)
 - Logging (optional): enable in Settings to track last 50/200/1000 sends, export CSV, clear log; includes failure details via wp_mail_failed

= 1.1.0 =
- Roles selection, subject/body templates with placeholders
- Branded HTML wrapper
- Test Email button (sends to site admin email)
- Security: nonce + capability check for Send Test Email
- Sanitization: roles, subject, message via register_setting callbacks
- Added placeholders: `[site_name]`, `[post_type]`, `[updated_at]`, `[author_name]`, `[post_edit_url]`
- New settings: Post Types filter and Exclude Updating User
- Developer hooks for placeholders, subject/message, recipients, headers, HTML wrapper, and send result
