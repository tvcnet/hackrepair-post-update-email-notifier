=== The Hack Repair Guy's Post Update Email Notifier ===

Contributors: hackrepair, tvcnet
Tags: email notifications, post update, roles, html email, admin
Donate link: https://hackrepair.com/about/hackrepair-plugin-archiver
Support link: https://www.reddit.com/user/hackrepair/comments/1ole6n1/new_plugin_post_update_email_notifier_branded/
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Send branded HTML emails to selected user roles whenever a published post or page is updated. Configure the subject and message templates, preview with a Test Email (AJAX), set email identity (From/Reply‑To), and control which post types trigger notifications. Includes options to exclude the updating user, optional logging + CSV export, a Reset to Defaults action, and developer hooks.

= Features =
- Branded HTML wrapper with customizable header text/color and footer (supports placeholders, incl. [year])
- Notify selected user roles on post/page updates (non‑autosave/revision)
- Configurable subject and message templates (HTML supported)
- Test Email (AJAX) to preview the current template — with inline success/error
- Post type filter and “Exclude Updating User” option
- Email Identity options for From/Reply‑To (no SMTP credentials)
- Optional logging of email send events (50/200/1000) with CSV export and Clear Log; captures failure details for plugin emails
- SMTP “Active” status chip + guidance notice after repeated failures
- Reset to Defaults action to restore all plugin options
- Smooth, accessible admin UX: card layout, sidebar nav, spinners, and inline “Done!”
- Developer hooks to customize placeholders, recipients, subject, headers, and final HTML

= How it works =
- On `post_updated`, if the post remains published, an email is sent to all users in the selected roles.
- Autosaves and revisions are skipped.
- If you select post types in Settings, only those types trigger notifications; otherwise, all types do.
- Optionally exclude the user who performed the update from recipients.
- The Test Email button sends a preview to your user email (falls back to the site admin email if your account has no email).
- If repeated mail failures are detected in the last 24 hours, an on‑page admin notice suggests installing an SMTP plugin.

= Admin UX =
- Section save actions run over AJAX with a small spinner and a green “Done!” indicator (no page reload)
- Clear Log and Reset provide inline feedback and spinners; CSV export triggers a normal file download

= Template placeholders =
- `[post_title]` — The post/page title
- `[post_url]` — The public permalink to the post/page
- `[editor_name]` — The display name of the user performing the update
- `[author_name]` — The post author’s display name
- `[post_type]` — The post type (singular label)
- `[site_name]` — Your site’s name
- `[updated_at]` — Update timestamp using your site’s date/time formats
- `[post_edit_url]` — Admin edit URL for the post
- `[year]` — Current 4‑digit year

= Developers =
Filters/actions available:
- `pue_should_notify( bool $should, int $post_ID, WP_Post $post_after, WP_Post $post_before )`
- `pue_placeholders( array $maps, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor )` (`$maps = ['subject'=>[], 'message'=>[]]`)
- `pue_email_subject( string $subject, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor, array $subject_map )`
- `pue_email_message( string $message, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor, array $message_map )`
- `pue_email_recipients( array $recipients, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor )`
- `pue_email_headers( array $headers, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor )`
- `pue_email_template_html( string $html, string $message, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor )`
- `pue_email_sent( bool $sent, array $recipients, string $subject, string $message, int $post_ID, WP_Post $post_after, WP_Post $post_before, WP_User $editor )`

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

Deleting the plugin removes its settings:

- `pue_roles`
- `pue_subject`
- `pue_message`
- `pue_post_types`
- `pue_exclude_updater`
 - `pue_enable_logging`
 - `pue_log_retention`
 - `pue_logs`
 - `pue_header_text`
 - `pue_header_bg_color`
 - `pue_footer_text`
 - `pue_from_name`
 - `pue_from_email`
 - `pue_reply_to_email`
 - `pue_smtp_notice_dismiss_until`

On multisite, these options are deleted from every site.

== Frequently Asked Questions ==

= When are emails sent? =
On the `post_updated` action, if the post is still published. Autosaves and revisions are skipped.

= Who receives the emails? =
All users in the selected roles (deduplicated). If enabled, the updating user is excluded.

= Does it work with custom post types? =
Yes. If you leave the “Post Types to Notify” section empty, all post types are eligible. Select specific post types to limit notifications.

= Does it work with SMTP plugins? =
Yes. The plugin uses `wp_mail()` so it’s compatible with SMTP plugins (WP Mail SMTP, Post SMTP, FluentSMTP, etc.). The settings page displays an “SMTP Active” status when SMTP is detected.

= How do I improve deliverability? =
Optional: For better deliverability, use an email address that matches your website’s domain. It’s less likely to land in spam or junk folders. Use an SMTP plugin (e.g., WP Mail SMTP) and verify domain authentication (SPF/DKIM). Check spam/junk folders when testing.

= Can I add a plain‑text alternative? =
Yes. Developers can enable a plain‑text alternative via `add_filter( 'pue_email_plaintext_enabled', '__return_true' );`. You can also adjust wrap width with `pue_email_plaintext_width` and customize the final text with `pue_email_plaintext`.

= How do I reset to defaults? =
Go to Settings → Post Update Notifier and click “Reset to Defaults” at the bottom. This restores all plugin options to their original defaults and clears the local log (posts/users are unaffected).

= Updates =
This plugin includes a lightweight self‑updater that checks a Google Drive manifest for new versions.

- Manifest format (hosted on Drive as a JSON file; public “Anyone with the link: Viewer”):
```
{ 
  "latest_version": "1.3.4",
  "download_file_id": "DRIVE_FILE_ID_FOR_THE_ZIP"
  // optional: "sha256": "64_hex_chars"
}
```
- Direct links (Drive):
  - Manifest: `https://drive.google.com/uc?export=download&id=MANIFEST_FILE_ID`
  - ZIP: `https://drive.google.com/uc?export=download&id=ZIP_FILE_ID`
- Caching: The manifest is cached for ~12 hours. Use the plugin’s Settings → Update Status (Cacheless) button to force a refresh.
- Integrity (optional): If you include `sha256` in the manifest, the ZIP’s SHA‑256 will be verified before install. If omitted, updates still work (hash is optional by default).

= Performance
For very large recipient lists, sending one email per recipient can take longer. Use an SMTP service/provider and, if needed, implement a queue/cron-based sending strategy via custom hooks.

= What data does the plugin store? =
Only WordPress options listed above and the optional recent email log (50/200/1000 entries). The log option is stored with autoload disabled.

= Security notes =
Admin actions require `manage_options` and are protected by nonces. All inputs pass through sanitizers; mail headers are built from sanitized values and From Name defensively strips CR/LF.
= Where are the settings? =
Go to Settings → Post Update Notifier. Configure roles, subject/body template, post types, and the exclude‑updater option. Use the Send Test Email button to preview.

== Installation ==

= Quick install from WordPress =
1. Go to Plugins → Add New.
2. Search for “The Hack Repair Guy’s Post Update Email Notifier”.
3. Install and Activate.

= Manual installation =
1. Upload the `hackrepair-post-update-email-notifier` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu

== Screenshots ==
1. Settings screen showing roles, post types, template, and options

== Changelog ==

= 1.4.0 =
- Architecture: start incremental OOP refactor with simple autoloader and new service classes (Logger, AdminInterface, NotificationService). Existing hooks, AJAX actions, and option names preserved.
- Settings: route registration and sanitizers via PUE_Settings_Manager (backward-compatible wrappers retained).
- Email: delegate composition and placeholder building via PUE_Email_Composer and PUE_Placeholder_Engine; public helper functions remain for back-compat.
- Delegation: route logging, CSV export, and `wp_mail_failed` capture through `PUE_Logger` (with fallback code paths for safety).
- Admin AJAX: route AJAX handlers (test email, force update check, bulk test, save sections) through `PUE_AdminInterface` without changing action names.
- Notifications: route update/test email flows through `PUE_NotificationService` while keeping identical behavior (one email per recipient; same filters/placeholders/headers).
- Fix: `pue_email_sent` now passes the composed HTML message instead of an undefined variable.
- No breaking changes.

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
