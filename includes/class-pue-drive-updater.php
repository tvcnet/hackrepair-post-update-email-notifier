<?php
// Simple Google Drive self-updater for this plugin.
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Drive_Updater')) {
    class PUE_Drive_Updater {
        protected static $config = [];
        protected static $manifest_cache_key = '';

        public static function boot(array $args) {
            // Required keys: plugin, slug, version, manifest_url
            $defaults = [
                'plugin'       => '',
                'slug'         => '',
                'version'      => '',
                'manifest_url' => '',
                'requires'     => '6.6',
                'tested'       => '6.8.2',
                'homepage'     => 'https://hackrepair.com/',
            ];
            self::$config = array_merge($defaults, $args);
            if (empty(self::$config['plugin']) || empty(self::$config['slug']) || empty(self::$config['version']) || empty(self::$config['manifest_url'])) {
                return;
            }
            self::$manifest_cache_key = 'pue_manifest_' . sanitize_key(self::$config['slug']);

            add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
            add_filter('plugins_api', [__CLASS__, 'plugins_api'], 10, 3);
            // Row action link removed per request; instant checks live in Settings UI
            // add_filter('plugin_action_links_' . self::$config['plugin'], [__CLASS__, 'action_links']);
            add_action('admin_init', [__CLASS__, 'handle_force_check']);
            // Verify package integrity before WordPress installs it
            add_filter('upgrader_pre_download', [__CLASS__, 'verify_and_download'], 10, 4);
        }

        protected static function manifest_direct_url($file_id) {
            $id = trim((string) $file_id);
            if ($id === '') return '';
            return 'https://drive.google.com/uc?export=download&id=' . rawurlencode($id);
        }

        protected static function fetch_manifest() {
            $cached = get_site_transient(self::$manifest_cache_key);
            if (is_array($cached) && isset($cached['latest_version']) && isset($cached['download_file_id'])) {
                return $cached;
            }
            $url = self::$config['manifest_url'];
            // Allow only HTTPS and drive.google.com for manifest
            $p = wp_parse_url($url);
            if (empty($p['scheme']) || strtolower($p['scheme']) !== 'https' || empty($p['host']) || !in_array(strtolower($p['host']), ['drive.google.com'], true)) {
                return $cached ?: null;
            }
            $resp = wp_remote_get($url, [
                'timeout' => 10,
                'redirection' => 3,
                'limit_response_size' => 65536,
                'reject_unsafe_urls' => true,
                'headers' => [ 'Accept' => 'application/json' ],
            ]);
            if (is_wp_error($resp)) return $cached ?: null;
            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code !== 200) return $cached ?: null;
            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['latest_version']) || empty($data['download_file_id'])) {
                return $cached ?: null;
            }
            // Validate manifest fields
            $ver = (string) $data['latest_version'];
            $id  = (string) $data['download_file_id'];
            if (!preg_match('/^\d+\.\d+\.\d+$/', $ver)) { return $cached ?: null; }
            if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) { return $cached ?: null; }
            if (isset($data['sha256'])) {
                $sha = (string) $data['sha256'];
                if (!preg_match('/^[A-Fa-f0-9]{64}$/', $sha)) {
                    unset($data['sha256']);
                }
            }
            // Record fetch time for admin display
            if (!isset($data['fetched_at'])) {
                $data['fetched_at'] = current_time('timestamp');
            }
            // Persist for 12 hours
            set_site_transient(self::$manifest_cache_key, $data, 12 * HOUR_IN_SECONDS);
            return $data;
        }

        /**
         * Verify and download package to a temp file before install.
         * If manifest provides a sha256, enforce it; can be made required via filter.
         */
        public static function verify_and_download($reply, $package, $upgrader, $hook_extra) {
            // Only intercept this plugin's updates
            $cfg = self::$config;
            $is_ours = false;
            if (is_array($hook_extra)) {
                if (!empty($hook_extra['plugin']) && $hook_extra['plugin'] === $cfg['plugin']) { $is_ours = true; }
                if (!$is_ours && !empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
                    $is_ours = in_array($cfg['plugin'], $hook_extra['plugins'], true);
                }
            }
            if (!$is_ours) { return $reply; }

            // Validate initial package URL host/scheme
            $p = wp_parse_url($package);
            if (empty($p['scheme']) || strtolower($p['scheme']) !== 'https' || empty($p['host'])) {
                return new WP_Error('pue_invalid_url', __('Updater: invalid download URL.', 'hackrepair-post-update-email-notifier'));
            }
            $host = strtolower($p['host']);
            $allowed_hosts = ['drive.google.com', 'googleusercontent.com'];
            if (!in_array($host, $allowed_hosts, true)) {
                return new WP_Error('pue_disallowed_host', __('Updater: disallowed download host.', 'hackrepair-post-update-email-notifier'));
            }

            $manifest = self::fetch_manifest();
            if (!$manifest) {
                return new WP_Error('pue_no_manifest', __('Updater: manifest unavailable.', 'hackrepair-post-update-email-notifier'));
            }
            $require_hash = apply_filters('pue_updater_require_hash', false, $manifest, $cfg);
            $expected = isset($manifest['sha256']) ? strtolower((string) $manifest['sha256']) : '';
            if ($require_hash && !$expected) {
                return new WP_Error('pue_missing_hash', __('Updater: package hash missing in manifest.', 'hackrepair-post-update-email-notifier'));
            }

            // Download to temp file
            $tmp = download_url($package, 30);
            if (is_wp_error($tmp)) { return $tmp; }

            if ($expected) {
                $actual = strtolower((string) @hash_file('sha256', $tmp));
                if (!$actual || $actual !== $expected) {
                    @unlink($tmp);
                    return new WP_Error('pue_hash_mismatch', __('Updater: package hash verification failed.', 'hackrepair-post-update-email-notifier'));
                }
            }
            // Return temp file path to let WP continue with install
            return $tmp;
        }

        /**
         * Force refresh the manifest cache and return latest data.
         * Used by AJAX to stay on the settings page.
         */
        public static function force_refresh() {
            // Clear both plugin update transient and manifest cache
            delete_site_transient('update_plugins');
            delete_site_transient(self::$manifest_cache_key);
            return self::fetch_manifest();
        }

        public static function check_for_update($transient) {
            if (!is_object($transient)) $transient = new stdClass();
            // If no plugins checked yet, return early
            if (empty($transient->checked) || !is_array($transient->checked)) {
                return $transient;
            }
            $cfg = self::$config;
            $manifest = self::fetch_manifest();
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }
            // Clear any stale response for our plugin to avoid false positives
            if (isset($transient->response[$cfg['plugin']])) {
                unset($transient->response[$cfg['plugin']]);
            }
            if (!$manifest) return $transient;
            $latest = (string) $manifest['latest_version'];
            $current = (string) $cfg['version'];
            if (version_compare($latest, $current, '>')) {
                $download_url = self::manifest_direct_url($manifest['download_file_id']);
                if ($download_url) {
                    $item = (object) [
                        'slug'        => $cfg['slug'],
                        'plugin'      => $cfg['plugin'],
                        'new_version' => $latest,
                        'package'     => $download_url,
                        'tested'      => $cfg['tested'],
                        'requires'    => $cfg['requires'],
                        'url'         => $cfg['homepage'],
                        'icons'       => [],
                    ];
                    $transient->response[$cfg['plugin']] = $item;
                }
            }
            return $transient;
        }

        public static function plugins_api($res, $action, $args) {
            $cfg = self::$config;
            if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $cfg['slug']) {
                return $res;
            }
            $manifest = self::fetch_manifest();
            $latest = $manifest && !empty($manifest['latest_version']) ? (string) $manifest['latest_version'] : $cfg['version'];
            $download_url = ($manifest && !empty($manifest['download_file_id'])) ? self::manifest_direct_url($manifest['download_file_id']) : '';
            $obj = (object) [
                'name'          => "The Hack Repair Guy's Post Update Email Notifier",
                'slug'          => $cfg['slug'],
                'version'       => $latest,
                'author'        => '<a href="https://hackrepair.com/">HackRepair.com</a>',
                'homepage'      => $cfg['homepage'],
                'requires'      => $cfg['requires'],
                'tested'        => $cfg['tested'],
                'download_link' => $download_url,
                'sections'      => [
                    'description' => '<p>Branded HTML email notifications to selected roles when posts/pages are updated.</p>',
                    'changelog'   => '<p>See plugin settings/readme for full changelog.</p>',
                ],
                'banners'       => [],
                'external'      => true,
            ];
            return $obj;
        }

        public static function action_links($links) {
            $url = add_query_arg(
                [ 'pue_force_check' => self::$config['slug'], '_wpnonce' => wp_create_nonce('pue_force_check') ],
                admin_url('plugins.php')
            );
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates now', 'hackrepair-post-update-email-notifier') . '</a>';
            return $links;
        }

        public static function handle_force_check() {
            if (!is_admin() || !current_user_can('update_plugins')) return;
            if (!isset($_GET['pue_force_check']) || !is_string($_GET['pue_force_check'])) return;
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'pue_force_check')) return;
            // Clear caches and trigger a fresh update check
            delete_site_transient('update_plugins');
            delete_site_transient(self::$manifest_cache_key);
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        }
    }
}
