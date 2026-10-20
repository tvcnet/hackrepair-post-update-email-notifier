# Google Drive Self‑Updater Blueprint

This document describes the small, self‑contained updater added to `hackrepair-post-update-email-notifier` v1.3.3, and how to reuse it in other plugins.

## Overview

The updater checks a Google Drive–hosted JSON manifest for a newer version and, if found, injects an update into WordPress’ standard plugin updater. It also provides an optional “Update Check (Instant)” button in the plugin’s settings to bypass the 12‑hour cache.

## Files

- `includes/class-pue-drive-updater.php` — core updater class
- (optional) Settings UI block that calls `wp_ajax_pue_force_check_now` to refresh the manifest inline

## Bootstrap

Add this in your main plugin file (adjust slug/manifest ID):

```php
add_action('admin_init', function(){
    if (!is_admin()) return;
    $manifest_id = 'YOUR_MANIFEST_FILE_ID';
    $manifest_url = 'https://drive.google.com/uc?export=download&id=' . $manifest_id;
    if (!class_exists('PUE_Drive_Updater')) {
        $path = __DIR__ . '/includes/class-pue-drive-updater.php';
        if (file_exists($path)) { require_once $path; }
    }
    if (class_exists('PUE_Drive_Updater')) {
        PUE_Drive_Updater::boot([
            'plugin'       => plugin_basename(__FILE__),
            'slug'         => dirname(plugin_basename(__FILE__)),
            'version'      => YOUR_PLUGIN_VERSION_CONST,
            'manifest_url' => $manifest_url,
            'requires'     => '6.6',
            'tested'       => '6.8.2',
            'homepage'     => 'https://example.com/',
        ]);
    }
});
```

## Manifest (Google Drive)

Create a JSON file in Drive and set “Anyone with the link: Viewer”. Keep the same file ID and use “Manage versions” to update contents.

```json
{
  "latest_version": "1.2.3",
  "download_file_id": "DRIVE_FILE_ID_FOR_THE_ZIP"
  // optional: "sha256": "64_hex_chars"
}
```

- Manifest direct URL: `https://drive.google.com/uc?export=download&id=MANIFEST_FILE_ID`
- ZIP direct URL: `https://drive.google.com/uc?export=download&id=ZIP_FILE_ID`

## Behavior

- Caching: Manifest cached ~12 hours (site transient). Use the plugin row link or settings button to force refresh.
- Update injection: Hooks `pre_set_site_transient_update_plugins` when `latest_version > installed_version`.
- Modal details: Hooks `plugins_api` to return description/changelog.
- Integrity (optional): If `sha256` is present, the updater verifies the ZIP before install (hook: `upgrader_pre_download`).

## Security

- URL validation: Manifest must be fetched over HTTPS from `drive.google.com`. ZIP must be served from `drive.google.com` or `googleusercontent.com`.
- Size/redirect limits: Manifest request limits redirects and response size.
- Capability: Force‑check AJAX requires `update_plugins` and a valid nonce.
- Optional integrity: Enable strict hash requirement if desired:

```php
add_filter('pue_updater_require_hash', '__return_true');
```

## Optional Settings UI (Instant Check)

You can add a simple card to your plugin’s settings with a button that calls the `pue_force_check_now` AJAX action to refresh the manifest and update the status inline (no navigation).

## Notes

- Keep ZIP sizes modest to avoid Drive’s interstitial pages.
- Always use HTTPS links.
- If you maintain multiple plugins, consider a central updater plugin that exposes a `register()` API; this self‑updater can be a fallback when the central plugin isn’t active.

