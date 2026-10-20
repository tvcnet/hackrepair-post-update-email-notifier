# Post Update Email Notifier (v1.4.4)

Branded, HTML email notifications when content updates — with a modern settings UI, logging, and a testing-only bulk sender for deliverability checks.

## Features
- Individual-only delivery: one email per recipient (privacy-friendly).
- Branded HTML wrapper: customizable header text/color and footer (supports placeholders, incl. `[year]`).
- Roles-based notifications on post/page updates (skips autosaves/revisions; only while published).
- Per‑post “Don’t send email notifications” checkbox in the editor (Classic + Block) for a one‑time skip.
- Configurable Subject and Message templates (HTML supported; placeholders supported throughout).
- Test Email (AJAX): inline success/error below the button; “Done!” indicator; no page jump.
- Bulk Test (Testing Only): send N individual test emails; optional plus-alias generation (name+1..+N@domain) and optional detailed logging.
- Post Type filter + “Exclude Updating User” option.
- Email Identity (From Name, From Email, Reply‑To); From Name supports placeholders; defensive header handling.
- Optional Logging (50/200/1000) with CSV export and Clear Log; failure details captured from `wp_mail_failed`; “View error” modal; “Not sent” entries recorded when an update is intentionally skipped.
- SMTP awareness: heuristic “Active” chip + guidance notice after repeated failures.
- Polished admin UI: card layout, compact sidebar nav with active state, consistent padding and spacing, buttons inside cards, inline “Done!”.
- Updates: Google Drive self‑updater with Settings → “Update Status (Cacheless)” to bypass cache.
- Branding UX: Desktop color swatch + WordPress color picker for the header background.

## Overview
On `post_updated`, when the post remains published, the plugin sends individualized HTML emails to all users in the selected roles. The message is wrapped in a branded header and footer. A Test Email action previews the current template. A testing-only Bulk Test card can exercise deliverability and error handling.

## Requirements
- WordPress 6.6+ and PHP 7.4+.
- Uses `wp_mail()`. For better deliverability, configure an SMTP plugin.

## Installation
1. Upload the `hackrepair-post-update-email-notifier` folder to `wp-content/plugins/` or upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Post Update Notifier.

## Settings
- Roles to Notify: Choose which roles receive updates.
- Post Types to Notify: Limit triggers to specific public types (empty = all).
- Exclude Updating User: Skip the current user from recipients.
- Subject: Supports placeholders.
- Message (HTML): Supports placeholders; auto-wrapped in the branded template.
- Test Email: AJAX send; notice appears directly under the button.
- Update Status (Cacheless): Force-refreshes the Drive manifest and update status without leaving the page.
- Logging: Enable + retention (50/200/1000); CSV export; Clear Log.
- Branding:
  - Header Text: Supports placeholders. Note: stored default uses `[site_name] Notification`, but fallback rendering uses `Post Update Notification`.
  - Header Background Color (with live color swatch and wp-color-picker).
  - Footer Text: Supports placeholders. Note: stored default uses `Thank you for supporting [site_name]`, but fallback rendering uses `Thank you for supporting us.`
- Email Identity:
  - From Name: Optional; supports placeholders like `[site_name]`, `[editor_name]`, `[year]`.
  - From Email: Optional; domain-aligned addresses help deliverability. SMTP plugins may override this.
  - Reply‑To Email: Optional.
- Reset to Defaults: Restores plugin options and clears local log.

### Placeholders
- `[post_title]`, `[post_url]`, `[editor_name]`, `[author_name]`, `[post_type]`, `[site_name]`, `[updated_at]`, `[post_edit_url]`, `[year]`

Default subject: `Post Updated: [post_title]`

Default message (concise):

```
<p>A post at [site_name] has been updated by [editor_name]:<br />
<strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>
```

## Behavior Details
- Trigger: `post_updated` (non‑autosave/revision; published only). Per‑post “Do Not Notify” skips the next update and then auto‑clears. When logging is enabled, the skip is recorded as “Not sent” in the Recent Email Log and CSV.
- Recipients: Deduplicated users across selected roles; optional exclude updater.
- Delivery: HTML emails with `Content-Type: text/html; charset=UTF-8`; individual sends only.
- Branding: Wrapper applies header/footer with placeholder support; color is sanitized.
- Test Email: Uses current template; sends to current user email (fallback to site admin).
- Bulk Test (testing only): Sends N individual test messages. Options:
  - Generate plus-aliases: create `name+1..+N@domain` to simulate many unique recipients landing in one inbox (works with providers honoring plus addressing).
  - Log detail: when unchecked, a single summary log row is written; when checked, one row per message is logged.
- Logging: Timestamps, type (update/test), sent/failed, recipients (sample), subject; `wp_mail_failed` details populate the error modal for plugin emails.
- SMTP: Heuristic “Active” badge; notice after 3+ failures in the last 24h (dismissable for 24h).
- Admin UX: No scroll jumps on actions; buttons live inside white card bodies; consistent paddings and spacing; per-card “Done!”.
- Updates: Manifest cached ~12h; force-refresh via Settings button or plugin row “Check for updates now”. Optional ZIP hash verification when `sha256` present in manifest.

## Troubleshooting
- Delivery failures: Configure SMTP; ensure From Email matches your domain.
- Roles: At least one role must be selected for notifications to send.
- Placeholders: Ensure `[post_url]` resolves with proper permalinks.
- Bulk Test: Providers may rate-limit; use reasonable counts. Plus-alias behavior varies by provider.

## Security Notes
- Capability: Settings require `manage_options`.
- CSRF: Nonces for all forms and AJAX.
- Sanitization: Roles, post types, subject, message (`wp_kses_post`), colors, booleans, retention, emails.
- Headers: From Name strips CR/LF; From/Reply‑To validated.
- CSV: Safe against formula injection by prefixing risky cells.
- Options: Logs stored with autoload disabled.

## Developer Hooks
- `pue_should_notify( $should, $post_ID, $post_after, $post_before )`
- `pue_placeholders( $maps, $post_ID, $post_after, $post_before, $editor )` where `$maps = ['subject'=>[], 'message'=>[]]`
- `pue_email_subject( $subject, $post_ID, $post_after, $post_before, $editor, $subject_map )`
- `pue_email_message( $message, $post_ID, $post_after, $post_before, $editor, $message_map )`
- `pue_email_recipients( $recipients, $post_ID, $post_after, $post_before, $editor )`
- `pue_email_headers( $headers, $post_ID, $post_after, $post_before, $editor )`
- `pue_email_template_html( $html, $message, $post_ID, $post_after, $post_before, $editor )`
- `pue_email_sent( $sent, $recipients, $subject, $message, $post_ID, $post_after, $post_before, $editor )`
- Plain‑text alternative (optional, off by default):
  - `pue_email_plaintext_enabled( $enabled, $html, $post_ID, $type )`
  - `pue_email_plaintext_width( $cols, $post_ID, $type )`
  - `pue_email_plaintext( $alt, $html, $post_ID, $type )`

## Roadmap / TODO
- Queueing/background sends for very large recipient lists (avoid timeouts).
- Optional custom recipients field (manual addresses) with validation.
- Multisite-specific notes and tests.
- Additional unit tests for sanitizers and logging flows.

## Performance Note
For very large recipient lists, individual sends can be slow. Use an SMTP service/provider and consider implementing batching/queueing via hooks and a scheduled task to keep admin actions fast.

## QA Checklist (v1.4.2)
- Notifications: Save via AJAX; values persist; Exclude Updater respected; individual sends; logs show sent/partial/failed and error modal works.
- Branding: Save via AJAX; placeholders render; header color applied.
- Email Identity: Save via AJAX; From/Reply‑To applied when valid; no header injection.
- Test Email: AJAX response with notice under button; “Done!” appears for that card only; no scroll jump.
- Logging: Enable toggle; retention 50/200/1000; CSV export; Clear Log in place; error detail modal shows when failures occur.
- Bulk Test: Individual sends; plus-alias generation optional; summary row when “Log detail” is off; per-message rows when on.
- Reset to Defaults: Confirms; resets all options; clears local log.
- UI Consistency: Buttons inside white card bodies; consistent spacing/padding; sidebar active state updates on scroll.
- Updates: Settings button refreshes “Latest available” + “Last checked” inline; after a check, the Plugins page shows available updates immediately.
- Mobile: Sidebar stacks under header; helper note drops below or hides; controls remain readable at small widths.
- Accessibility: Button contrast passes AA; clear focus rings; reduced-motion respected.

- 1.4.2 — Fix: register “Don’t send” flag for all public post types so Gutenberg persists it across Pages/CPTs; Enhancement: log Status “Not sent” and export `skipped` rows when the per‑post skip is used.
- 1.4.1 — Per‑post “Don’t send email notifications” checkbox; REST‑exposed post meta `_pue_do_not_notify`; `pue_should_notify` guard.
- 1.3.7 — Uninstall cleans all plugin options; default message uses HTML line breaks; minor logging copy tweaks.
- 1.3.6 — Mobile-first layout and accessibility improvements (stacked sidebar, higher-contrast buttons, focus rings, reduced motion).
- 1.3.4 — Update Status (Cacheless) button to force-refresh manifest; instant check refreshes status inline and triggers immediate plugin list visibility.
- 1.3.3 — Google Drive self‑updater added; Settings “Update Check (Instant)” (AJAX) + optional SHA‑256 verification; host/manifest validation.
- 1.3.1 — Docs consistency, performance note added; individual-only delivery reaffirmed.
- 1.3.0 — Individual‑only delivery; Bulk Test card with plus‑aliases; improved logging with error modal.
- 1.2.14 — Testing‑only: initial Bulk Test card (AJAX).
- 1.2.12 — Security hardening; centralized save helpers; logs autoload=no.
- 1.2.11 — AJAX saves for Notifications/Branding/Identity; spinner + Done!.
- 1.2.10 — Reset to Defaults spinner; CSV spinner.
- 1.2.8–1.2.9 — Test Email over AJAX with spinner.
- 1.2.4–1.2.5 — Branding options; Email Identity; SMTP notice.
- Earlier — Initial roles/templates and branded wrapper.

## License
GPLv2 (as noted in the plugin header)

## Support
Reddit thread: https://github.com/tvcnet/hackrepair-post-update-email-notifier/discussions
