<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Settings_Manager')) {
    class PUE_Settings_Manager {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function register_settings() {
            // Notifications + Logging
            register_setting(
                'pue_group_notifications',
                'pue_roles',
                [
                    'type' => 'array',
                    'sanitize_callback' => 'pue_sanitize_roles',
                    'default' => [],
                ]
            );
            register_setting(
                'pue_group_notifications',
                'pue_subject',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_subject',
                    'default' => 'Post Updated: [post_title]',
                ]
            );
            register_setting(
                'pue_group_notifications',
                'pue_message',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_message',
                    'default' => '<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>',
                ]
            );

            // Post type filters and exclude-updater toggle
            register_setting(
                'pue_group_notifications',
                'pue_post_types',
                [
                    'type' => 'array',
                    'sanitize_callback' => 'pue_sanitize_post_types',
                    'default' => [],
                ]
            );
            register_setting(
                'pue_group_notifications',
                'pue_exclude_updater',
                [
                    'type' => 'boolean',
                    'sanitize_callback' => 'pue_sanitize_bool',
                    'default' => 0,
                ]
            );
            register_setting(
                'pue_group_notifications',
                'pue_enable_logging',
                [
                    'type' => 'boolean',
                    'sanitize_callback' => 'pue_sanitize_bool',
                    'default' => 0,
                ]
            );
            register_setting(
                'pue_group_notifications',
                'pue_log_retention',
                [
                    'type' => 'integer',
                    'sanitize_callback' => 'pue_sanitize_retention',
                    'default' => 50,
                ]
            );

            // Email Identity
            register_setting(
                'pue_group_identity',
                'pue_from_name',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_subject',
                    'default' => '',
                ]
            );
            register_setting(
                'pue_group_identity',
                'pue_from_email',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_email',
                    'default' => '',
                ]
            );
            register_setting(
                'pue_group_identity',
                'pue_reply_to_email',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_email',
                    'default' => '',
                ]
            );

            // Branding
            register_setting(
                'pue_group_branding',
                'pue_header_text',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_subject',
                    'default' => '[site_name] Notification',
                ]
            );
            register_setting(
                'pue_group_branding',
                'pue_header_bg_color',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_hex',
                    'default' => '#0073aa',
                ]
            );
            register_setting(
                'pue_group_branding',
                'pue_footer_text',
                [
                    'type' => 'string',
                    'sanitize_callback' => 'pue_sanitize_subject',
                    'default' => 'Thank you for supporting [site_name]',
                ]
            );
        }

        // Sanitizers
        public function sanitize_roles($input) {
            $allowed = array_keys(wp_roles()->roles);
            $sanitized = [];
            if (is_array($input)) {
                foreach ($input as $role) {
                    $role = sanitize_key($role);
                    if (in_array($role, $allowed, true)) {
                        $sanitized[] = $role;
                    }
                }
            }
            return array_values(array_unique($sanitized));
        }

        public function sanitize_subject($input) {
            if ($input === null) { return ''; }
            if (!is_scalar($input)) { return ''; }
            return sanitize_text_field((string) $input);
        }

        public function sanitize_message($input) {
            if ($input === null) { return ''; }
            if (!is_string($input)) { $input = (string) $input; }
            return wp_kses_post($input);
        }

        public function sanitize_post_types($input) {
            $allowed = get_post_types([], 'names');
            $sanitized = [];
            if (is_array($input)) {
                foreach ($input as $pt) {
                    $pt = sanitize_key($pt);
                    if (in_array($pt, $allowed, true)) {
                        $sanitized[] = $pt;
                    }
                }
            }
            return array_values(array_unique($sanitized));
        }

        public function sanitize_bool($input) {
            return $input ? 1 : 0;
        }

        public function sanitize_retention($input) {
            $allowed = [50, 200, 1000];
            $val = (int) $input;
            return in_array($val, $allowed, true) ? $val : 50;
        }

        public function sanitize_hex($color) {
            $color = is_string($color) ? $color : '';
            $c = sanitize_hex_color($color);
            return $c ? $c : '#0073aa';
        }

        public function sanitize_email($input) {
            if ($input === null) return '';
            if (!is_string($input)) $input = (string) $input;
            return sanitize_email($input);
        }
    }
}

