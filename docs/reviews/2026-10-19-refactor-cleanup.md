# Post Update Email Notifier: refactor and package review

Reviewed October 19, 2026. Review only: runtime code, assets, installed WordPress and published packages were not changed.

## Baseline and verification

- Current source: main at f45e062, clean before this review.
- Downloaded the actual GitHub v1.5.0 release asset, rather than rebuilding or overwriting the existing ZIP.
- Published ZIP SHA-256: a4176bee2ed1a97555e4567927ad9d71fa311fe3e8f0af617cc5a7dadd28e6bf, matching GitHub's asset digest.
- 151 packaged files, one hackrepair-post-update-email-notifier/ root. No .git, .github, docs, tests, scripts, releases, backups or dist directories.
- Current packaged-source paths differing from v1.5.0: readme.txt and includes/class-pue-admin-interface.php, reflecting the previously committed Discussions support changes. No unreleased CSS changes.
- Existing checks pass: 13 notification assertions, updater asset guards, seven update-message checks, package-permissions regression, JavaScript syntax for both scripts, shared PHPCS on 21 configured first-party PHP files.
- No fresh browser walkthrough, real mail send or deployed-site regression was performed for this review. Static checks and isolated reproductions do not establish visual or production clearance.

## 1. Fix before the next release: CSV export fatal error

**Location:** includes/class-pue-logger.php:108; duplicate fallback in includes/class-pue-admin-interface.php:65.

The active logger calls gmgmdate(), which is not defined by this plugin. Invoking export_csv_and_exit() from the downloaded release with stubbed option/header helpers reproducibly throws `Error: Call to undefined function gmgmdate()` before CSV rows are emitted. This affects the Export Log action, including an empty log. PHP syntax and PHPCS do not detect this undefined function call.

**Update needed:** use gmdate() for the filename, retain one export implementation, and add a regression that actually exercises a successful export in a subprocess (the method exits). Cover empty/populated output and spreadsheet-safe fields. Also verify the real WordPress download path: the current POST handler runs inside the settings renderer, where admin markup may already have been emitted. Move response handling before page output if that test confirms header/body contamination.

## 2. Correct feedback while simplifying admin JavaScript

**Locations:** assets/js/admin.js:193 onward and :319 onward.

The single test-email handler appends a green Done! indicator whenever JSON resolves, without checking res.success. The server can return success=false, including mail failure, so the UI can show an error message alongside a success indicator. Several network-error catch handlers silently ignore failures. The update handler leaves prior failure feedback in place after a later successful check.

**Update needed:** derive completion feedback from the response result, show a visible request error, and clear stale errors on retry/success. Verify unsuccessful JSON, rejected requests and successful retries without sending real external mail.

**Obsolete code:** remove the pue-download-latest element lookup and hide branch (:319, :334-336); the current settings renderer does not render that button. The bulkNonce value localized in includes/admin.php:44 has no consumer in the shipped JavaScript; the bulk form already supplies its nonce. Preserve the nonce field and server verification.

## 3. Consolidate leftover fallback implementations

**Locations:** includes/helpers.php:87-185 and :197-208; includes/sanitizers.php; includes/logging.php; includes/class-pue-admin-interface.php:58-100.

The current autoloader supplies the service classes. Nevertheless, global wrappers retain complete copies of placeholder generation, sanitation, logging and CSV logic for the old non-class implementation. These branches duplicate maintained service methods and can diverge; the export typo is already present in both copies. They are fallback branches, not evidence that every global function is unused.

**Update needed:** preserve existing global function names, signatures and WordPress hooks, but delegate to the canonical service implementations. Check that required class files are complete in packages and run focused sanitation, placeholder and logging regressions before dropping fallback bodies. Do not remove public wrappers based only on repository reference counts.

pue_get_related_error() has no internal caller; the UI uses pue_get_related_error_detail(). Keep or deprecate the public helper deliberately rather than silently breaking external integrations. Likewise, pue_email_template_branded_legacy() is still the active template implementation through its wrapper; its name alone does not make it removable.

## 4. Clean CSS without changing the design

**Location:** assets/css/admin.css.

- Lines 11-12 define primary-button styles that later identical-specificity rules at 93-94 override. Remove the obsolete earlier declarations and preserve the accessible final colors.
- --pue-bg and --pue-muted are declared but never consumed. --pue-accent2 duplicates --pue-accent and can be merged after replacing its one use.
- The .pue-reset rule at :55 is empty. Its class remains in markup; remove the empty rule, retaining the hook if compatibility is desired.
- The helper note's inline layout declarations in class-pue-admin-interface.php:188 are then overridden by !important responsive rules. Consolidate the static layout into the stylesheet, retaining desktop/tablet/mobile behavior.

**Details CSS:** assets/brand/thrg-details.css contains shared with-banner heart/title overlay styling that assets/css/pue-plugin-details.css suppresses for this plugin's baked-in banner. Simplify the plugin-specific cascade without removing the intentional no-banner fallback or hiding the accessible heading from assistive technology. Make stylesheet dependencies/order explicit if consolidating.

**Verification needed after edits:** settings and View Details at desktop and narrow widths, original banner, focus indicators, reduced motion, helper-note breakpoint behavior and error modal.

### Confirmed layout mismatch: section headings and bodies

User screenshot shows Test Email and Update status headings extending past their card bodies. Source confirms the cause: `.pue-card` in assets/css/admin.css:53 has `max-width:1000px`, while `.pue-theme h2` and the settings form tables fill the flexible `.pue-main` column. At wider viewport sizes, section headers and bodies therefore have different right edges. This is a visible layout defect, not merely redundant CSS.

**Update needed:** make section headings, card bodies and form tables share the available content-column width. Remove the card-only width cap and apply consistent border-box sizing; preserve the sidebar and mobile stacking. Check the log table's separate inline max-width in class-pue-admin-interface.php:364 and constrain overflow locally if needed. Verify matching left/right edges for Notifications, Branding, Email Identity, Test Email, Update status, Bulk Test, Logs, Reset and Support at desktop/tablet/mobile widths. Do not change the full-width email template as part of this admin-page correction.

## 5. Optional package reduction: duplicate image bytes

assets/icon-128x128.png is byte-identical to assets/brand/thrg-heart-128.png (23,082 bytes). The two corresponding 256px files are also identical (75,304 bytes). Both pairs currently have real references: plugin-information icons, settings heading and fallback-details CSS.

**Optional update:** point those consumers at one canonical file per size before removing duplicates. Raw file reduction would be 98,386 bytes; ZIP reduction will differ. This is a small package optimization, not a demonstrated runtime bottleneck. Check update-list icons, native details and settings before removing any asset.

## Keep in the release

- WordPress color-picker dependency: the branding control uses wpColorPicker and its swatch.
- Error-modal CSS and JavaScript: used by delivery-failure log links.
- Editor script and nonce-protected non-JavaScript form paths.
- Reduced-motion rules, local heart assets, original banner and no-banner fallback.
- Complete required Plugin Update Checker dependencies, including its nested vendor directory.
- Public compatibility functions, settings keys, meta key and developer hooks.

## Suggested execution order

1. Repair and cover CSV export and misleading AJAX feedback.
2. Remove obsolete download-button code and unused localized value.
3. Consolidate duplicate service fallbacks while preserving public APIs.
4. Consolidate CSS; deduplicate image assets only after reference changes and visual verification.
5. Re-run shared PHPCS and meaningful regressions, then validate on Zendogs with Mailpit. Consider Codex Security review separately; do not run it automatically.
6. Publish a new patch version only after the reviewed changes are approved. Do not replace v1.5.0 or its release assets.

## Execution status (October 19, 2026)

Items 1-4 implemented in the working tree as 1.5.1; item 5 deferred. Verification results are recorded in PROJECT.md under "1.5.1 review candidate". Publication awaits approval of the review build.
