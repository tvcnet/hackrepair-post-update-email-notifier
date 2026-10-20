# Execution evidence: October 19, 2026

- Pre-change working tree and .git preserved in outer backups/2026-10-19-baseline/source-before-standardization.tar.gz. Original outer 1.4.4 ZIPs and artwork retained.
- Canonical source restructured into bootstrap + focused includes, history docs, review/plan, scripts/tests/releases/dist.
- Shared PHPCS: 21 files, no errors/warnings after formatting and targeted fixes. Scoped annotations preserve legacy local-time log semantics and PHPMailer's fixed property names; they are not general suppression rules.
- Notification regressions: 13 checks cover skip defaults, opt-in resetting, roles/dedup/editor exclusion, domain+port subjects, full-width wrapper, autosave/revision/unpublished/no-role guards, valid/invalid test recipients and missing-template defaults.
- Updater tests reject absent/mismatched assets, source-archive fallback and unsupported checker APIs. Message tests cover rate-limit vs other errors and escaped diagnostics.
- Isolated ZIP regression packages source mode 0600 as 0644 without modifying source files. Full builder verifies single root, bytes, complete vendored updater and modes; no .git/docs/dev files included.
- Browser: Settings and native View Details load; desktop and 390px widths checked without horizontal overflow. Test button reports acceptance without delivery guarantee. GitHub unavailable error is visible rather than swallowed.
- Zendogs real update hook: temporary fixture creation silent; explicit opt-in update sent one captured message; next update skipped. Original role/exclusion/log options restored; fixture deleted. No real posts/users changed.
- Mailpit captured test preview 5yLcRtjwXvF2i9jFSpFj4K and corrected update fixture 4ny1N0lpGG6Zsz712BKQXd. Messages show domain/plugin subjects and full-width HTML. No external-delivery claim. No bulk send performed.
- Repository remains tvcnet. GitHub currently has no releases; publication/install-via-published-updater verification is pending review approval. Old Google Drive manifest unchanged.

See PROJECT.md for limitations and the final source state. Consider Codex Security review separately; it has not been run automatically.

Observed runtime: WordPress 7.1, PHP 8.2.33. Tested-up-to metadata is 7.1 for this review candidate; 7.4/8.3 CI is configured but not yet observed.

A stale November 2025 rebase-merge directory was preserved in the outer baseline backup and cleared with git rebase --quit. HEAD, worktree and index were preserved. The rebuild is isolated on codex/1.5.0-standardization, keeping main and published releases unchanged.

The final family guide includes all four companion plugins; native plugin-information output was checked after adding the fourth card.
