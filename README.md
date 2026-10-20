<p align="center"><img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench" width="144" height="144"></p>

<h1 align="center">The Hack Repair Guy's Post Update Email Notifier</h1>

Notify selected WordPress roles when published content is updated. Customize the email, exclude the updating user, review optional logs, and test your mail configuration.

**Version 1.5.0.** [Download the latest release](https://github.com/tvcnet/hackrepair-post-update-email-notifier/releases/latest). Older Google Drive installations require a one-time manual ZIP update to receive the GitHub updater.

## Features

- Individual emails to users in selected roles; optional updating-user exclusion and public post-type selection.
- Per-post **Don’t send email notifications** defaults to checked. Explicitly uncheck it for an update you want to announce; the flag returns to skip afterward.
- Customizable HTML message, header/footer, and From/Reply-To settings. Existing templates and options are preserved.
- Subjects identify the configured domain and plugin: `[example.com] [Post Update Email Notifier] Post Updated: …`.
- Full-width email wrapper, template placeholders, and optional plain-text alternative.
- Single-recipient test and a separate **Bulk Test (Testing Only)** control. Test buttons send real mail through WordPress; use a local catcher for development.
- Optional Recent Email Log with 50/200/1000 retention, CSV export, failure context and skipped entries. Acceptance for sending does not confirm delivery.
- Native View Details, Other THRG Plugins, local branding assets and reduced-motion support.
- GitHub release updates from the existing `tvcnet/hackrepair-post-update-email-notifier` repository.

## Installation and use

Upload the release ZIP through Plugins → Add New → Upload Plugin. The installed folder remains `hackrepair-post-update-email-notifier`; the entrypoint remains `post-update-email-notifier.php`. Open **Settings → Post Update Notifier**. No notification roles are selected by default. Configure and test mail before enabling notifications. A PHP/WordPress update can invoke hooks outside the editor: leave default skip behavior intact unless intentionally announcing an update.

Requirements remain WordPress 6.6+ and PHP 7.4+. The verified local runtime is WordPress 7.1 / PHP 8.2.33; see PROJECT.md for the evidence and limitations.

## Privacy and updates

Recipient addresses, subjects and content/context can appear in optional logs. Review retention and exports accordingly. Messages use the site's WordPress mail configuration; no SMTP credentials are stored by this plugin. GitHub update checks reveal standard connection information such as the server IP, but do not include post content or recipient lists.

The updater accepts only a published release with matching `vX.Y.Z` tag and uploaded `hackrepair-post-update-email-notifier.X.Y.Z.zip`. It rejects source-archive fallbacks. Older Drive-based installations require a one-time manual ZIP update, or an explicitly approved bridge manifest; see [migration notes](docs/plans/1.5.0-standardization.md).

## Development

Read [PROJECT.md](PROJECT.md), [MAINTAINING.md](MAINTAINING.md) and [the implementation plan](docs/plans/1.5.0-standardization.md). Run the shared PHPCS, PHP regressions and permission-safe builder. Consider Codex Security review separately; do not run that tool automatically.

## Changelog

See the **Changelog** section of [readme.txt](readme.txt). Historical project notes are in docs/history and are not current implementation instructions.

## Support and license

[GitHub repository](https://github.com/tvcnet/hackrepair-post-update-email-notifier) · [Issues](https://github.com/tvcnet/hackrepair-post-update-email-notifier/issues) · [The Hack Repair Guy](https://hackrepair.com/)

GPLv2. See LICENSE; the original license.txt is retained.
