# Maintenance and release procedure

Work from the nested Git checkout. Inspect git status before editing; preserve pre-existing local changes. Read PROJECT.md and docs/plans/1.5.0-standardization.md. Update the plan and evidence as work proceeds.

## Checks

```sh
/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"
python3 tests/package-permissions.py
php tests/notifications.php
php tests/update-notices.php
php tests/updater.php
python3 scripts/build-release.py
```

Lint all first-party PHP and JavaScript and packaged PHP. PHPCBF is for explicitly authorized code updates, never check-only reviews. Use the shared installation; do not add local/global standards copies. Do not weaken checks merely to get a green report. Scoped exceptions need a concrete reason.

## Packaging

Update plugin header, PUE_VERSION and readme stable tag together. Record release notes under releases/vX.Y.Z.md; public README links to the changelog rather than repeating version history. The builder always uses folder hackrepair-post-update-email-notifier and asset hackrepair-post-update-email-notifier.X.Y.Z.zip. Build from the complete tree, apply .distignore, preserve nested updater dependencies, verify all permissions as 0644 and source bytes, and write SHA-256. Preserve all old ZIPs and checksums. Never ship Git history, docs, backups, source artwork, tests, config or credentials. Never upload a build before review approval.

## Test environment

Zendogs: localhost:8081, container zendogs-wp-sandbox-wordpress-1. Mailpit: localhost:8025. A local MU plugin captures WordPress mail; confirm it is present before sending any test. No production testing, real account resets, external recipients or bulk sends. Back up existing plugin/options before a review deployment; use clearly labeled disposable fixtures and restore temporary options.

## Publication

Keep tvcnet/hackrepair-post-update-email-notifier. Require user approval of the concrete review build before creating a release. Tag vX.Y.Z and upload the versioned ZIP plus checksum. Verify main/tag commit, uploaded digest, actual updater URL, installation, activation, asset HTTP responses and View Details. Record proof separately from local/static checks. Update older Drive installations manually unless a bridge manifest is explicitly authorized.

Consider a Codex Security diff review; do not run it automatically.
