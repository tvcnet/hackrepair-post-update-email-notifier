<?php
/*
Plugin Name: The Hack Repair Guy's Post Update Email Notifier
Plugin URI: https://www.reddit.com/user/hackrepair/comments/1ole6n1/new_plugin_post_update_email_notifier_branded/
Description: Sends an HTML email to selected user roles whenever a post or page is updated. Includes settings to configure roles, subject, message, branding, email identity, and a test email.
Version: 1.3.6
Author: Jim Walker, The Hack Repair Guy
Author URI: https://hackrepair.com/
License: GPL2
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Text Domain: hackrepair-post-update-email-notifier
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

if (!defined('PUE_VERSION')) {
    define('PUE_VERSION', '1.3.6');
}

// ---------- 0. Load Text Domain and Plugin Links ----------
function pue_load_textdomain() {
    load_plugin_textdomain('hackrepair-post-update-email-notifier', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'pue_load_textdomain');

function pue_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=pue-settings')) . '">' . esc_html__('Settings', 'hackrepair-post-update-email-notifier') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pue_plugin_action_links');

// ---------- 0.1 Enqueue Admin Assets ----------
function pue_admin_enqueue_assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'settings_page_pue-settings') {
        wp_enqueue_style('pue-admin', plugins_url('assets/css/admin.css', __FILE__), [], PUE_VERSION);
        wp_enqueue_script('pue-admin', plugins_url('assets/js/admin.js', __FILE__), [], PUE_VERSION, true);
        wp_localize_script('pue-admin', 'PUE_Ajax', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('pue_test_email_action'),
            'bulkNonce' => wp_create_nonce('pue_bulk_test_action'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'pue_admin_enqueue_assets');

// Ensure certain options are created with autoload disabled (performance/privacy)
add_action('init', function(){
    if (false === get_option('pue_logs', false)) {
        add_option('pue_logs', [], '', 'no');
    }
});

// ---------- 1. Register Settings ----------
function pue_register_settings() {
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
            'default' => '<p>A post at [site_name] has been updated by [editor_name]:</br><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>',
        ]
    );

    // New: post type filters and exclude-updater toggle
    register_setting(
        'pue_group_notifications',
        'pue_post_types',
        [
            'type' => 'array',
            'sanitize_callback' => 'pue_sanitize_post_types',
            'default' => [], // Empty = all types allowed (back-compat)
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

    // Branding (moved from init to admin_init for consistency)
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
add_action('admin_init', 'pue_register_settings');

// ---------- 2. Create Admin Menu ----------
function pue_create_menu() {
    add_options_page(
        __('Post Update Notifier Settings', 'hackrepair-post-update-email-notifier'),
        __('Post Update Notifier', 'hackrepair-post-update-email-notifier'),
        'manage_options',
        'pue-settings',
        'pue_settings_page'
    );
}
add_action('admin_menu', 'pue_create_menu');

// ---------- 3. Settings Page ----------
function pue_settings_page() {
    $roles = wp_roles()->roles;
    $selected_roles = get_option('pue_roles', []);
    $subject = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
    $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:</br><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
    $selected_post_types = get_option('pue_post_types', []);
    $exclude_updater = (int) get_option('pue_exclude_updater', 0);
    $enable_logging = (int) get_option('pue_enable_logging', 0);
    $log_retention = (int) get_option('pue_log_retention', 50);
    $logs = get_option('pue_logs', []);
    $post_type_objects = get_post_types(['public' => true], 'objects');
    $test_notice_html = '';
    $test_pressed = false;
    $clear_pressed = false;
    $reset_pressed = false;
    $settings_updated = !empty($_GET['settings-updated']);

    // Handle test email send
    if (isset($_POST['pue_test_email'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'hackrepair-post-update-email-notifier'));
        }
        check_admin_referer('pue_test_email_action', 'pue_test_email_nonce');
        $current_user = wp_get_current_user();
        $to_email = !empty($current_user->user_email) ? $current_user->user_email : get_option('admin_email');
        $sent = pue_send_test_email($to_email, $subject, $message);
        $test_pressed = true;
        if ($sent) {
            $test_notice_html = '<div class="pue-notice pue-notice--success"><span class="pue-notice__emoji">🎉</span><p><strong>' . esc_html__('Success!', 'hackrepair-post-update-email-notifier') . '</strong> ' . sprintf(esc_html__('Test email sent to %s.', 'hackrepair-post-update-email-notifier'), esc_html($to_email)) . '</p></div>';
        } else {
            $test_notice_html = '<div class="pue-notice pue-notice--error"><span class="pue-notice__emoji">💥</span><p><strong>' . esc_html__('Hmm,', 'hackrepair-post-update-email-notifier') . '</strong> ' . sprintf(esc_html__('looks like the test email to %s didn’t go through. Double-check your mail setup or try using an SMTP plugin for more reliable delivery.', 'hackrepair-post-update-email-notifier'), esc_html($to_email)) . '</p></div>';
        }
    }
    // Handle export log (CSV)
    if (isset($_POST['pue_export_log'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'hackrepair-post-update-email-notifier'));
        }
        check_admin_referer('pue_export_log_action', 'pue_export_log_nonce');
        $export_logs = get_option('pue_logs', []);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=post-update-email-notifier-log-' . gmdate('Ymd-His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['time','type','status','post_id','post_title','post_type','editor_id','recipients','subject']);
        foreach ((array) $export_logs as $entry) {
            $time = isset($entry['ts']) ? (int) $entry['ts'] : time();
            $type = isset($entry['type']) ? (string) $entry['type'] : 'update';
            $sent = !empty($entry['sent']) ? 'sent' : 'failed';
            $post_id = isset($entry['post_id']) ? (int) $entry['post_id'] : 0;
            $title = pue_csv_safe(isset($entry['post_title']) ? (string) $entry['post_title'] : '');
            $ptype = pue_csv_safe(isset($entry['post_type']) ? (string) $entry['post_type'] : '');
            $editor_id = isset($entry['editor_id']) ? (int) $entry['editor_id'] : 0;
            $recips = isset($entry['recipients']) && is_array($entry['recipients']) ? implode('; ', array_map('pue_csv_safe', $entry['recipients'])) : '';
            $subject_line = pue_csv_safe(isset($entry['subject']) ? (string) $entry['subject'] : '');
            fputcsv($out, [
                date_i18n('Y-m-d H:i:s', $time),
                $type,
                $sent,
                $post_id,
                $title,
                $ptype,
                $editor_id,
                $recips,
                $subject_line,
            ]);
        }
        fclose($out);
        exit;
    }
    // Handle clear log
    if (isset($_POST['pue_clear_log'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'hackrepair-post-update-email-notifier'));
        }
        check_admin_referer('pue_clear_log_action', 'pue_clear_log_nonce');
        delete_option('pue_logs');
        $logs = [];
        $clear_pressed = true;
        echo '<div class="pue-notice pue-notice--success"><span class="pue-notice__emoji">🧹</span><p><strong>' . esc_html__('Clean!', 'hackrepair-post-update-email-notifier') . '</strong> ' . esc_html__('Log cleared.', 'hackrepair-post-update-email-notifier') . '</p></div>';
    }

    // Handle reset to defaults
    if (isset($_POST['pue_reset_defaults'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to perform this action.', 'hackrepair-post-update-email-notifier'));
        }
        check_admin_referer('pue_reset_defaults_action', 'pue_reset_defaults_nonce');
        $to_delete = [
            // Core settings
            'pue_roles', 'pue_subject', 'pue_message', 'pue_post_types', 'pue_exclude_updater',
            'pue_enable_logging', 'pue_log_retention', 'pue_logs',
            // Branding
            'pue_header_text', 'pue_header_bg_color', 'pue_footer_text',
            // Identity
            'pue_from_name', 'pue_from_email', 'pue_reply_to_email',
            // Notices
            'pue_smtp_notice_dismiss_until',
        ];
        foreach ($to_delete as $opt) { delete_option($opt); }
        echo '<div class="pue-notice pue-notice--success"><span class="pue-notice__emoji">↩️</span><p><strong>' . esc_html__('Reset complete.', 'hackrepair-post-update-email-notifier') . '</strong> ' . esc_html__('All settings have been restored to their defaults.', 'hackrepair-post-update-email-notifier') . '</p></div>';
        $reset_pressed = true;
        // Refresh local variables
        $selected_roles   = get_option('pue_roles', []);
        $subject          = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
        $message          = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:</br><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
        $selected_post_types = get_option('pue_post_types', []);
        $exclude_updater  = (int) get_option('pue_exclude_updater', 0);
        $enable_logging   = (int) get_option('pue_enable_logging', 0);
        $log_retention    = (int) get_option('pue_log_retention', 50);
        $logs             = get_option('pue_logs', []);
    }
    $smtp_active = pue_is_smtp_active();
    $site_domain = parse_url(home_url(), PHP_URL_HOST);
    ?>
    <div class="wrap">
        
        <h1><?php echo esc_html__('Post Update Notifier Settings', 'hackrepair-post-update-email-notifier'); ?>
            <?php if ($smtp_active): ?>
                <span class="pue-chip pue-chip--ok"><span class="pue-dot"></span><?php echo esc_html__('SMTP Active', 'hackrepair-post-update-email-notifier'); ?></span>
            <?php else: ?>
                <span class="pue-chip pue-chip--muted"><span class="pue-dot pue-dot--muted"></span><?php echo esc_html__('SMTP Not Detected', 'hackrepair-post-update-email-notifier'); ?></span>
            <?php endif; ?>
        </h1>
        <div class="pue-container">
          <aside class="pue-sidebar">
            <ul class="pue-nav" id="pue-nav">
              <li><a href="#notifications">🔔 <?php echo esc_html__('Notifications', 'hackrepair-post-update-email-notifier'); ?></a></li>
              <li><a href="#branding">🎨 <?php echo esc_html__('Branding', 'hackrepair-post-update-email-notifier'); ?></a></li>
              <li><a href="#identity">📧 <?php echo esc_html__('Email Identity', 'hackrepair-post-update-email-notifier'); ?></a></li>
              <li><a href="#test-email">✉️ <?php echo esc_html__('Test Email', 'hackrepair-post-update-email-notifier'); ?></a></li>
              <li><a href="#logging">🧾 <?php echo esc_html__('Logging', 'hackrepair-post-update-email-notifier'); ?></a></li>
              <li><a href="#support">🆘 <?php echo esc_html__('Support', 'hackrepair-post-update-email-notifier'); ?></a></li>
              
            </ul>
          </aside>
          <div class="pue-main">
        <div class="pue-theme">
        <h2 id="notifications"><?php echo esc_html__('Notifications', 'hackrepair-post-update-email-notifier'); ?></h2>
        <form id="pue-form-notifications" method="post" action="options.php">
            <?php settings_fields('pue_group_notifications'); ?>
            <?php wp_nonce_field('pue_save_notifications', 'pue_save_notifications_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Select Roles to Notify:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <div class="pue-right-note" style="float:right; width:40%; max-width:520px; padding:12px 14px; margin:0 24px 10px 16px; background:#fffaf2; border:1px solid #f3d19c; border-radius:6px;">
                            <p style="margin:0 0 8px 0;">
                                <?php echo wp_kses_post(__('The <strong>Hack Repair Guy’s Post Update Email Notifier</strong> keeps your team in the loop by sending branded HTML updates whenever posts or pages are modified.', 'hackrepair-post-update-email-notifier')); ?>
                            </p>
                            <p style="margin:0; color:#6b6b6b;">
                                <?php echo esc_html__('Tip: Uncheck all roles or post types to pause notifications — they’ll stay off until you turn them back on.', 'hackrepair-post-update-email-notifier'); ?>
                            </p>
                        </div>
                        <?php foreach ($roles as $key => $role): ?>
                            <label>
                                <input type="checkbox" name="pue_roles[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_roles)); ?>>
                                <?php echo esc_html($role['name']); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Post Types to Notify:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="hidden" name="pue_post_types[]" value="">
                        <?php if (!empty($post_type_objects)) : ?>
                            <?php foreach ($post_type_objects as $pt) : if ($pt->name === 'attachment') continue; ?>
                                <label>
                                    <input type="checkbox" name="pue_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, (array) $selected_post_types, true)); ?>>
                                    <?php echo esc_html($pt->labels->singular_name ?: $pt->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description"><?php echo esc_html__('Leave all unchecked to notify on all post types.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Email Subject:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td><input type="text" name="pue_subject" value="<?php echo esc_attr($subject); ?>" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Email Message (HTML):', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <textarea name="pue_message" rows="8" cols="70"><?php echo esc_textarea($message); ?></textarea>
                        <p class="description"><?php echo wp_kses_post(__('Available placeholders: <code>[post_title]</code>, <code>[post_url]</code>, <code>[editor_name]</code>, <code>[site_name]</code>, <code>[post_type]</code>, <code>[updated_at]</code>, <code>[author_name]</code>, <code>[post_edit_url]</code>, <code>[year]</code>', 'hackrepair-post-update-email-notifier')); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Exclude Updating User:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <label>
                            <input type="hidden" name="pue_exclude_updater" value="0">
                            <input type="checkbox" name="pue_exclude_updater" value="1" <?php checked(1, $exclude_updater); ?>>
                            <?php echo esc_html__('Do not send the notification to the person who updated the post.', 'hackrepair-post-update-email-notifier'); ?>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Enable Logging:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <label>
                            <input type="hidden" name="pue_enable_logging" value="0">
                            <input type="checkbox" name="pue_enable_logging" value="1" <?php checked(1, $enable_logging); ?>>
                            <?php echo esc_html__('Keep the last 50 email send events in a local log.', 'hackrepair-post-update-email-notifier'); ?>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Log Retention:', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <select name="pue_log_retention">
                            <?php foreach ([50,200,1000] as $opt): ?>
                                <option value="<?php echo (int) $opt; ?>" <?php selected((int)$log_retention, (int)$opt); ?>><?php echo (int) $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Number of recent log entries to keep.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"></th>
                    <td>
                        <?php submit_button(__('Save Notifications', 'hackrepair-post-update-email-notifier'),'primary','submit', false); ?>
                    </td>
                </tr>
            </table>
            </form>

        <h2 id="branding"><?php echo esc_html__('Branding', 'hackrepair-post-update-email-notifier'); ?></h2>
        <form id="pue-form-branding" method="post" action="options.php">
            <?php settings_fields('pue_group_branding'); ?>
            <?php wp_nonce_field('pue_save_branding', 'pue_save_branding_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Header Text', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="text" name="pue_header_text" value="<?php echo esc_attr(get_option('pue_header_text', 'Post Update Notification')); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Text shown in the colored header bar. Supports all placeholders.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Header Background Color', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="color" name="pue_header_bg_color" value="<?php echo esc_attr(get_option('pue_header_bg_color', '#0073aa')); ?>" />
                        <p class="description"><?php echo esc_html__('Background color for the email header bar.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Footer Text', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="text" name="pue_footer_text" value="<?php echo esc_attr(get_option('pue_footer_text', 'Thank you for supporting us.')); ?>" class="regular-text" />
                        <p class="description"><?php echo wp_kses_post(__('Footer text. Supports all placeholders.', 'hackrepair-post-update-email-notifier')); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"></th>
                    <td>
                        <?php submit_button(esc_html__('Save Branding', 'hackrepair-post-update-email-notifier'),'primary','submit', false); ?>
                    </td>
                </tr>
            </table>
            </form>

        <h2 id="identity"><?php echo esc_html__('Email Identity', 'hackrepair-post-update-email-notifier'); ?></h2>
        <form id="pue-form-identity" method="post" action="options.php">
            <?php settings_fields('pue_group_identity'); ?>
            <?php wp_nonce_field('pue_save_identity', 'pue_save_identity_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('From Name', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="text" name="pue_from_name" value="<?php echo esc_attr(get_option('pue_from_name', '')); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Optional. Overrides the sender name. Supports all placeholders.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('From Email', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="email" name="pue_from_email" value="<?php echo esc_attr(get_option('pue_from_email', '')); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__("Optional: For better deliverability, use an email address that matches your website's domain. Messages sent this way are less likely to end up in spam or junk folders. If you're using an SMTP plugin, note that its settings may override the email address entered here.", 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Reply-To Email', 'hackrepair-post-update-email-notifier'); ?></th>
                    <td>
                        <input type="email" name="pue_reply_to_email" value="<?php echo esc_attr(get_option('pue_reply_to_email', '')); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Optional. Where replies should go.', 'hackrepair-post-update-email-notifier'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"></th>
                    <td>
                        <?php submit_button(esc_html__('Save Email Identity', 'hackrepair-post-update-email-notifier'),'primary','submit', false); ?>
                    </td>
                </tr>
            </table>
        </form>

        <h2 id="test-email"><?php echo esc_html__('Test Email', 'hackrepair-post-update-email-notifier'); ?></h2>
        <div class="pue-card">
            <div class="pue-inline" style="padding:8px 0 0 0;">
                <form id="pue-test-form" method="post" style="display:inline;">
                    <?php wp_nonce_field('pue_test_email_action', 'pue_test_email_nonce'); ?>
                    <input type="hidden" name="pue_test_email" value="1">
                    <?php submit_button(esc_html__('Send a Test', 'hackrepair-post-update-email-notifier'), 'primary', 'submit', false); ?>
                </form>
                <?php if ($test_pressed) { echo '<span class="pue-done" role="status" aria-live="polite">' . esc_html__('Done!', 'hackrepair-post-update-email-notifier') . '</span>'; } ?>
                <span class="description" style="margin-left:10px"><?php echo esc_html__('Send a sample email to your address to preview the current template.', 'hackrepair-post-update-email-notifier'); ?></span>
            </div>
            <div id="pue-test-result"><?php if ( ! empty( $test_notice_html ) ) { echo $test_notice_html; } ?></div>
        </div>
        <?php if ($enable_logging): ?>
            <h2 id="logging"><?php echo esc_html__('Recent Email Log', 'hackrepair-post-update-email-notifier'); ?></h2>
            <div class="pue-card">
            <?php if (empty($logs)): ?>
                <p><?php echo esc_html__('No log entries yet.', 'hackrepair-post-update-email-notifier'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width: 1000px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Time', 'hackrepair-post-update-email-notifier'); ?></th>
                            <th><?php echo esc_html__('Type', 'hackrepair-post-update-email-notifier'); ?></th>
                            <th><?php echo esc_html__('Status', 'hackrepair-post-update-email-notifier'); ?></th>
                            <th><?php echo esc_html__('Post', 'hackrepair-post-update-email-notifier'); ?></th>
                            <th><?php echo esc_html__('Recipients', 'hackrepair-post-update-email-notifier'); ?></th>
                            <th><?php echo esc_html__('Subject', 'hackrepair-post-update-email-notifier'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $display = array_reverse($logs);
                        $display = array_slice($display, 0, 20);
                        foreach ($display as $entry):
                            $time = isset($entry['ts']) ? (int) $entry['ts'] : time();
                            $type = isset($entry['type']) ? $entry['type'] : 'update';
                            $sent = !empty($entry['sent']);
                            $post_id = isset($entry['post_id']) ? (int) $entry['post_id'] : 0;
                            $post_title = isset($entry['post_title']) ? $entry['post_title'] : '';
                            $recips = isset($entry['recipients']) && is_array($entry['recipients']) ? $entry['recipients'] : [];
                            $subject_line = isset($entry['subject']) ? $entry['subject'] : '';
                            $time_str = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $time);
                            $post_label = $post_id ? ($post_title ? $post_title : ('#' . $post_id)) : __('(no post)', 'hackrepair-post-update-email-notifier');
                            $recip_display = implode(', ', array_slice($recips, 0, 3));
                            if (count($recips) > 3) { $recip_display .= ', …'; }
                        ?>
                        <tr>
                            <td><?php echo esc_html($time_str); ?></td>
                            <td><?php echo esc_html(ucfirst($type)); ?></td>
                            <td>
                                <?php
                                $status_html = '';
                                if ($sent) {
                                    $status_html = '<span style="color:green;">' . esc_html__('Sent', 'hackrepair-post-update-email-notifier') . '</span>';
                                } else {
                                    $sc = isset($entry['sent_count']) ? (int) $entry['sent_count'] : 0;
                                    $tot = isset($entry['total']) ? (int) $entry['total'] : 0;
                                    if ($sc > 0 && $tot > 0 && $sc < $tot) {
                                        $status_html = '<span style="color:#d98300;">' . esc_html__('Partial', 'hackrepair-post-update-email-notifier') . ' (' . (int) $sc . '/' . (int) $tot . ')</span>';
                                    } else {
                                        $status_html = '<span style="color:#b32d2e;">' . esc_html__('Failed', 'hackrepair-post-update-email-notifier') . '</span>';
                                    }
                                    // Append a modal trigger for detailed error
                                    if (function_exists('pue_get_related_error_detail')) {
                                        $detail = pue_get_related_error_detail($logs, $type, $post_id, $time);
                                        if (!empty($detail['full'])) {
                                            $mode = isset($entry['mode']) ? (string) $entry['mode'] : '';
                                            $status_html .= ' <a href="#" class="pue-err-link" data-error-full="' . esc_attr($detail['full']) . '" data-error-code="' . esc_attr($detail['code']) . '" data-error-time="' . esc_attr($time_str) . '" data-error-mode="' . esc_attr($mode) . '">' . esc_html__('View error', 'hackrepair-post-update-email-notifier') . '</a>';
                                        }
                                    }
                                }
                                echo $status_html;
                                ?>
                            </td>
                            <td><?php echo $post_id ? '<a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html($post_label) . '</a>' : esc_html($post_label); ?></td>
                            <td><?php echo esc_html($recip_display); ?></td>
                            <td><?php echo esc_html(wp_html_excerpt($subject_line, 80, '…')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pue-inline" style="padding-top:10px;">
                    <form id="pue-form-clear-log" method="post" style="display:inline-block; margin-right:8px;">
                        <?php wp_nonce_field('pue_clear_log_action', 'pue_clear_log_nonce'); ?>
                        <input type="hidden" name="pue_clear_log" value="1" />
                        <?php submit_button(esc_html__('Clear Log', 'hackrepair-post-update-email-notifier'), 'primary', 'submit', false); ?>
                        <?php if ($clear_pressed) { echo '<span class="pue-done" role="status" aria-live="polite">' . esc_html__('Done!', 'hackrepair-post-update-email-notifier') . '</span>'; } ?>
                    </form>
                    <form id="pue-form-export-csv" method="post" style="display:inline-block;">
                        <?php wp_nonce_field('pue_export_log_action', 'pue_export_log_nonce'); ?>
                        <input type="hidden" name="pue_export_log" value="1" />
                        <?php submit_button(esc_html__('Export CSV', 'hackrepair-post-update-email-notifier'), 'primary', 'submit', false); ?>
                    </form>
                </div>
            <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2 id="updates"><?php echo esc_html__('Update Status (Cacheless)', 'hackrepair-post-update-email-notifier'); ?></h2>
        <div class="pue-card">
            <?php
            $slug = dirname(plugin_basename(__FILE__));
            $manifest_key = 'pue_manifest_' . sanitize_key($slug);
            $manifest = get_site_transient($manifest_key);
            $current_v = defined('PUE_VERSION') ? PUE_VERSION : (string) get_file_data(__FILE__, ['Version' => 'Version'])['Version'];
            $latest_v = (is_array($manifest) && !empty($manifest['latest_version'])) ? (string) $manifest['latest_version'] : '';
            $fetched_at = (is_array($manifest) && !empty($manifest['fetched_at'])) ? (int) $manifest['fetched_at'] : 0;
            ?>
            <p><strong><?php echo esc_html__('Current version:', 'hackrepair-post-update-email-notifier'); ?></strong> <span class="pue-updates-current"><?php echo esc_html($current_v); ?></span></p>
            <p><strong><?php echo esc_html__('Latest available (Drive):', 'hackrepair-post-update-email-notifier'); ?></strong> <span class="pue-updates-latest"><?php echo $latest_v ? esc_html($latest_v) : esc_html__('Not checked yet', 'hackrepair-post-update-email-notifier'); ?></span></p>
            <p><strong><?php echo esc_html__('Last checked:', 'hackrepair-post-update-email-notifier'); ?></strong> <span class="pue-updates-fetched"><?php echo $fetched_at ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $fetched_at)) : esc_html__('—', 'hackrepair-post-update-email-notifier'); ?></span></p>
            <p class="pue-inline" style="margin-top:8px;">
                <button type="button" id="pue-check-updates-btn" class="button button-primary"><?php echo esc_html__('Check for updates now', 'hackrepair-post-update-email-notifier'); ?></button>
                <input type="hidden" id="pue-force-check-nonce" value="<?php echo esc_attr( wp_create_nonce('pue_force_check') ); ?>" />
            </p>
        </div>

        <h2 id="bulk-test"><?php echo esc_html__('Bulk Test (Testing Only)', 'hackrepair-post-update-email-notifier'); ?></h2>
        <div class="pue-card">
            <p class="description" style="margin:8px 0 12px 0;">
                <?php echo esc_html__('Enter an email address and a count to send multiple test messages (individual sends) for deliverability and error testing. Uses the current template. Please respect provider rate limits.', 'hackrepair-post-update-email-notifier'); ?>
            </p>
            <form id="pue-form-bulk-test" method="post" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <?php wp_nonce_field('pue_bulk_test_action', 'pue_bulk_test_nonce'); ?>
                <label><?php echo esc_html__('Email:', 'hackrepair-post-update-email-notifier'); ?>
                    <input type="email" name="pue_bulk_email" required placeholder="you@example.com" />
                </label>
                <label><?php echo esc_html__('Count:', 'hackrepair-post-update-email-notifier'); ?>
                    <input type="number" name="pue_bulk_count" min="1" max="200" value="50" />
                </label>
                <label style="margin-left:6px;">
                    <input type="checkbox" name="pue_bulk_plus" value="1">
                    <?php echo esc_html__('Generate plus-aliases (name+1..+N@domain) — useful for Gmail to see N deliveries in one inbox.', 'hackrepair-post-update-email-notifier'); ?>
                </label>
                <label>
                    <input type="checkbox" name="pue_bulk_log_detail" value="1">
                    <?php echo esc_html__('Log detail (one row per message)', 'hackrepair-post-update-email-notifier'); ?>
                </label>
                <?php submit_button(esc_html__('Run Bulk Test', 'hackrepair-post-update-email-notifier'), 'primary', 'submit', false); ?>
            </form>
            <div id="pue-bulk-result" style="margin-top:10px;"></div>
        </div>

        <h2 id="reset-defaults"><?php echo esc_html__('Reset to Defaults', 'hackrepair-post-update-email-notifier'); ?></h2>
        <div class="pue-card pue-reset">
            <p><strong><?php echo esc_html__('Restore Default Settings', 'hackrepair-post-update-email-notifier'); ?>:</strong>
            <?php echo esc_html__('This will reset all plugin settings to their original defaults, including Notifications, Branding, Email Identity, and Logging. Your posts and users are not affected.', 'hackrepair-post-update-email-notifier'); ?></p>
            <form id="pue-form-reset-defaults" method="post" style="margin-top:10px; display:inline-block;">
                <?php wp_nonce_field('pue_reset_defaults_action', 'pue_reset_defaults_nonce'); ?>
                <input type="hidden" name="pue_reset_defaults" value="1" />
                <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to defaults?', 'hackrepair-post-update-email-notifier')); ?>');"><?php echo esc_html__('Reset to Defaults', 'hackrepair-post-update-email-notifier'); ?></button>
                <?php if ($reset_pressed) { echo '<span class="pue-done" role="status" aria-live="polite">' . esc_html__('Done!', 'hackrepair-post-update-email-notifier') . '</span>'; } ?>
            </form>
        </div>

        <h2 id="support"><?php echo esc_html__('Support', 'hackrepair-post-update-email-notifier'); ?></h2>
        <div class="pue-card">
            <p>☕ <?php echo wp_kses_post(__('If you found this plugin helpful, please <a href="https://hackrepair.com/donations/buy-jim-a-coffee" target="_blank" rel="noopener">buy Jim a cup of coffee?</a>', 'hackrepair-post-update-email-notifier')); ?></p>
            <p><span class="dashicons dashicons-editor-help"></span> <?php echo wp_kses_post(__('Support and Updates: If you want to receive notifications about updates or leave feedback on the plugin, <a href="https://www.reddit.com/user/hackrepair/comments/1ole6n1/new_plugin_post_update_email_notifier_branded/" target="_blank" rel="noopener">subscribe to the official support thread</a>.', 'hackrepair-post-update-email-notifier')); ?></p>
        </div>

        

        </div><!-- .pue-theme -->
          </div><!-- .pue-main -->
        </div><!-- .pue-container -->

        <!-- Error Modal -->
        <div id="pue-modal" class="pue-modal" aria-hidden="true" role="dialog" aria-modal="true" style="display:none;">
            <div class="pue-modal__backdrop" data-modal-close="1"></div>
            <div class="pue-modal__dialog" role="document">
                <div class="pue-modal__header">
                    <h3 class="pue-modal__title"><?php echo esc_html__('Delivery Error', 'hackrepair-post-update-email-notifier'); ?></h3>
                    <button type="button" class="pue-modal__close" aria-label="<?php echo esc_attr__('Close', 'hackrepair-post-update-email-notifier'); ?>" data-modal-close="1">&times;</button>
                </div>
                <div class="pue-modal__body">
                    <p class="pue-modal__line"><strong><?php echo esc_html__('Code:', 'hackrepair-post-update-email-notifier'); ?></strong> <span id="pue-error-code"></span></p>
                    <p class="pue-modal__line"><strong><?php echo esc_html__('When:', 'hackrepair-post-update-email-notifier'); ?></strong> <span id="pue-error-time"></span></p>
                    <p class="pue-modal__line"><strong><?php echo esc_html__('Mode:', 'hackrepair-post-update-email-notifier'); ?></strong> <span id="pue-error-mode"></span></p>
                    <pre id="pue-error-full" class="pue-modal__pre" style="white-space:pre-wrap"></pre>
                </div>
            </div>
        </div>

    </div>
    <?php
}

// ---------- 3.1 Self-Updater (Google Drive manifest) ----------
add_action('admin_init', function(){
    if (!is_admin()) return;
    $manifest_id = '1XaCSjEWjuXD87Q0oCFJLgGLUkBdo3pvL'; // update-info.json (Drive file ID)
    $manifest_url = 'https://drive.google.com/uc?export=download&id=' . $manifest_id;
    if (!class_exists('PUE_Drive_Updater')) {
        $path = __DIR__ . '/includes/class-pue-drive-updater.php';
        if (file_exists($path)) { require_once $path; }
    }
    if (class_exists('PUE_Drive_Updater')) {
        PUE_Drive_Updater::boot([
            'plugin'       => plugin_basename(__FILE__),
            'slug'         => dirname(plugin_basename(__FILE__)),
            'version'      => PUE_VERSION,
            'manifest_url' => $manifest_url,
            'requires'     => '6.6',
            'tested'       => '6.8.2',
            'homepage'     => 'https://hackrepair.com/',
        ]);
    }
});

// ---------- 4. Send Email on Post Update ----------
function pue_notify_on_update($post_ID, $post_after, $post_before) {
    if (wp_is_post_autosave($post_ID) || wp_is_post_revision($post_ID)) return;
    if ($post_after->post_status !== 'publish') return;
    if (!apply_filters('pue_should_notify', true, $post_ID, $post_after, $post_before)) return;

    // Respect post type filters if configured
    $allowed_types = get_option('pue_post_types', []);
    if (!empty($allowed_types) && !in_array($post_after->post_type, (array) $allowed_types, true)) {
        return;
    }

    $selected_roles = get_option('pue_roles', []);
    if (empty($selected_roles)) return;

    $subject_template = get_option('pue_subject');
    $message_template = get_option('pue_message');

    $editor = wp_get_current_user();
    $post_title = $post_after->post_title;

    // Build placeholder maps for update context and allow developer overrides
    $maps = pue_build_placeholder_maps_update($post_ID, $post_after, $post_before, $editor);
    $maps = apply_filters('pue_placeholders', $maps, $post_ID, $post_after, $post_before, $editor);

    // Compose email (subject, body HTML, headers) via centralized helper
    list($subject, $body_html, $headers) = pue_compose_email(
        'update',
        $subject_template,
        $message_template,
        $maps,
        [ 'post_id' => (int) $post_ID, 'post_after' => $post_after, 'post_before' => $post_before ],
        $editor
    );

    $recipients = [];
    foreach ($selected_roles as $role) {
        $users = get_users(['role' => $role]);
        foreach ($users as $user) {
            $recipients[] = $user->user_email;
        }
    }

    // Optionally exclude the user who updated the post
    $exclude_updater = (int) get_option('pue_exclude_updater', 0);
    if ($exclude_updater && !empty($editor->user_email)) {
        $recipients = array_filter($recipients, function($email) use ($editor) {
            return strtolower($email) !== strtolower($editor->user_email);
        });
    }

    $recipients = array_unique($recipients);
    $recipients = apply_filters('pue_email_recipients', $recipients, $post_ID, $post_after, $post_before, $editor);
    $recipients = pue_sanitize_emails((array) $recipients);
    if (!empty($recipients)) {
        // Send individually only (privacy, deliverability, observability)
        $all_ok = true;
        $sent_count = 0;
        $total = count($recipients);
        foreach ($recipients as $rcpt) {
            $ok = wp_mail($rcpt, $subject, $body_html, $headers);
            if ($ok) { $sent_count++; } else { $all_ok = false; }
        }
        $sent = $all_ok;
        pue_log_event('update', $sent, $recipients, $subject, $post_ID, [
            'post_title' => $post_title,
            'post_type'  => $post_after->post_type,
            'editor_id'  => $editor->ID,
            'mode'       => 'individual',
            'sent_count' => isset($sent_count) ? (int) $sent_count : ($sent ? count($recipients) : 0),
            'total'      => isset($total) ? (int) $total : count($recipients),
        ]);
        do_action('pue_email_sent', $sent, $recipients, $subject, $message, $post_ID, $post_after, $post_before, $editor);
    }
    return $post_ID;
}
add_action('post_updated', 'pue_notify_on_update', 10, 3);

// ---------- 5. Send Test Email ----------
function pue_send_test_email($to, $subject, $message, $log = true) {
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'X-PUE: 1',
        'X-PUE-Type: test',
        'X-PUE-Post: 0',
    ];
    // Provide filter parity with update sends (post-specific args are 0/nulls here)
    $user = wp_get_current_user();
    // Build maps and compose via centralized helper for parity with update sends
    $maps = pue_build_placeholder_maps_test($user);
    list($subject, $body_html, $headers) = pue_compose_email(
        'test',
        $subject,
        $message,
        $maps,
        [ 'post_id' => 0 ],
        $user
    );
    $to = sanitize_email($to);
    if (!is_email($to)) { return false; }
    $sent = wp_mail($to, 'Test: ' . $subject, $body_html, $headers);
    if ($log) {
        pue_log_event('test', $sent, [$to], 'Test: ' . $subject, 0, []);
    }
    return $sent;
}

// AJAX handler for Test Email (admin)
add_action('wp_ajax_pue_send_test_email', 'pue_ajax_send_test_email');
function pue_ajax_send_test_email() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error([ 'html' => '<div class="pue-notice pue-notice--error"><p>' . esc_html__('Permission denied.', 'hackrepair-post-update-email-notifier') . '</p></div>' ], 403);
    }
    // Nonce can be passed in body as 'nonce'
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'pue_test_email_action')) {
        wp_send_json_error([ 'html' => '<div class="pue-notice pue-notice--error"><p>' . esc_html__('Security check failed.', 'hackrepair-post-update-email-notifier') . '</p></div>' ], 400);
    }
    $subject = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
    $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:</br><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
    $current_user = wp_get_current_user();
    $to_email = !empty($current_user->user_email) ? $current_user->user_email : get_option('admin_email');
    $sent = pue_send_test_email($to_email, $subject, $message);
    if ($sent) {
        $html = '<div class="pue-notice pue-notice--success"><span class="pue-notice__emoji">🎉</span><p><strong>' . esc_html__('Success!', 'hackrepair-post-update-email-notifier') . '</strong> ' . sprintf(esc_html__('Test email sent to %s.', 'hackrepair-post-update-email-notifier'), esc_html($to_email)) . '</p></div>';
        wp_send_json_success([ 'html' => $html ]);
    }
    $html = '<div class="pue-notice pue-notice--error"><span class="pue-notice__emoji">💥</span><p><strong>' . esc_html__('Hmm,', 'hackrepair-post-update-email-notifier') . '</strong> ' . sprintf(esc_html__('looks like the test email to %s didn’t go through. Double-check your mail setup or try using an SMTP plugin for more reliable delivery.', 'hackrepair-post-update-email-notifier'), esc_html($to_email)) . '</p></div>';
    wp_send_json_error([ 'html' => $html ]);
}

// AJAX: Force update check (stay on page)
add_action('wp_ajax_pue_force_check_now', 'pue_ajax_force_check_now');
function pue_ajax_force_check_now() {
    if (!current_user_can('update_plugins')) {
        wp_send_json_error([ 'msg' => 'forbidden' ], 403);
    }
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'pue_force_check')) {
        wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
    }
    if (!class_exists('PUE_Drive_Updater')) {
        $path = __DIR__ . '/includes/class-pue-drive-updater.php';
        if (file_exists($path)) require_once $path;
    }
    if (!class_exists('PUE_Drive_Updater')) {
        wp_send_json_error([ 'msg' => 'no_updater' ], 500);
    }
    $manifest = PUE_Drive_Updater::force_refresh();
    // Also trigger WordPress' plugin update check so the Plugins list reflects updates immediately
    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache(true);
    }
    if (function_exists('wp_update_plugins')) {
        wp_update_plugins();
    }
    if (!$manifest || empty($manifest['latest_version'])) {
        wp_send_json_error([ 'msg' => 'no_manifest' ], 500);
    }
    $latest = (string) $manifest['latest_version'];
    $ts     = !empty($manifest['fetched_at']) ? (int) $manifest['fetched_at'] : current_time('timestamp');
    $dl     = !empty($manifest['download_file_id']) ? ('https://drive.google.com/uc?export=download&id=' . rawurlencode((string) $manifest['download_file_id'])) : '';
    $last_html = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts);
    wp_send_json_success([
        'latest_version' => $latest,
        'last_checked'   => $last_html,
        'download_url'   => $dl,
    ]);
}

// AJAX: Bulk Test Email
add_action('wp_ajax_pue_bulk_test_send', 'pue_ajax_bulk_test_send');
function pue_ajax_bulk_test_send() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error([ 'error' => __('Permission denied.', 'hackrepair-post-update-email-notifier') ], 403);
    }
    $nonce = isset($_POST['pue_bulk_test_nonce']) ? $_POST['pue_bulk_test_nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
    if (!wp_verify_nonce($nonce, 'pue_bulk_test_action')) {
        wp_send_json_error([ 'error' => __('Security check failed.', 'hackrepair-post-update-email-notifier') ], 400);
    }
    $email = isset($_POST['pue_bulk_email']) ? sanitize_email($_POST['pue_bulk_email']) : '';
    $count = isset($_POST['pue_bulk_count']) ? (int) $_POST['pue_bulk_count'] : 0;
    $use_plus = !empty($_POST['pue_bulk_plus']);
    $log_detail = !empty($_POST['pue_bulk_log_detail']);
    if (!$email || !is_email($email)) {
        wp_send_json_error([ 'error' => __('Invalid email address.', 'hackrepair-post-update-email-notifier') ], 400);
    }
    if ($count < 1) { $count = 1; }
    if ($count > 200) { $count = 200; }

    $subject = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
    $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:</br><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));

    // Individual-only bulk test
    $sent = 0; $failed = 0;
    $targets = [];
    if ($use_plus) {
        $targets = pue_generate_plus_aliases($email, $count);
    } else {
        for ($i=0; $i<$count; $i++) { $targets[] = $email; }
    }
    $i = 0;
    foreach ($targets as $addr) {
        $i++;
        // Suppress per-message logs unless detailed logging requested
        $ok = pue_send_test_email($addr, '[Bulk ' . $i . '] ' . $subject, $message, $log_detail ? true : false);
        if ($ok) { $sent++; } else { $failed++; }
        usleep(50000);
    }
    // Summary row when not logging detail
    if (!$log_detail) {
        pue_log_event('test', ($sent === count($targets)), [$email], '[Bulk Individual Summary] ' . $subject, 0, [
            'mode'       => 'bulk_individual_summary',
            'sent_count' => (int) $sent,
            'total'      => (int) count($targets),
        ]);
    }
    $html = '<div class="pue-notice ' . ($failed ? 'pue-notice--warning' : 'pue-notice--success') . '"><p>'
          . sprintf( esc_html__('Bulk test complete (Individual). Sent %1$d of %2$d.', 'hackrepair-post-update-email-notifier'), (int)$sent, (int)count($targets) )
          . '</p></div>';
    wp_send_json_success([ 'html' => $html, 'sent' => $sent, 'total' => count($targets), 'mode' => 'individual' ]);
}

/**
 * Generate plus-aliases for a base email. Example: name@example.com => name+1@example.com ... name+N@example.com
 */
function pue_generate_plus_aliases($email, $count) {
    $email = sanitize_email($email);
    $count = (int) $count; if ($count < 1) $count = 1; if ($count > 500) $count = 500;
    $parts = explode('@', $email);
    if (count($parts) !== 2) { return array_fill(0, $count, $email); }
    list($local, $domain) = $parts;
    // Strip existing +tag if present
    $base = $local;
    $plusPos = strpos($local, '+');
    if ($plusPos !== false) { $base = substr($local, 0, $plusPos); }
    $out = [];
    for ($i=1; $i<=$count; $i++) {
        $alias = $base . '+' . $i . '@' . $domain;
        $alias = sanitize_email($alias);
        if ($alias) { $out[] = $alias; }
    }
    return $out ?: array_fill(0, $count, $email);
}

// AJAX: save Notifications
add_action('wp_ajax_pue_save_notifications', 'pue_ajax_save_notifications');
function pue_ajax_save_notifications() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error([ 'msg' => 'forbidden' ], 403);
    }
    if (!isset($_POST['pue_save_notifications_nonce']) || !wp_verify_nonce($_POST['pue_save_notifications_nonce'], 'pue_save_notifications')) {
        wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
    }
    // Sanitize and save
    $roles       = isset($_POST['pue_roles']) ? pue_sanitize_roles($_POST['pue_roles']) : [];
    $subject     = isset($_POST['pue_subject']) ? pue_sanitize_subject($_POST['pue_subject']) : '';
    $message     = isset($_POST['pue_message']) ? pue_sanitize_message($_POST['pue_message']) : '';
    $post_types  = isset($_POST['pue_post_types']) ? pue_sanitize_post_types($_POST['pue_post_types']) : [];
    $exclude_upd = isset($_POST['pue_exclude_updater']) ? pue_sanitize_bool($_POST['pue_exclude_updater']) : 0;
    $enable_log  = isset($_POST['pue_enable_logging']) ? pue_sanitize_bool($_POST['pue_enable_logging']) : 0;
    $retention   = isset($_POST['pue_log_retention']) ? pue_sanitize_retention($_POST['pue_log_retention']) : 50;

    pue_save_notifications_group([
        'pue_roles' => $roles,
        'pue_subject' => $subject,
        'pue_message' => $message,
        'pue_post_types' => $post_types,
        'pue_exclude_updater' => (int)$exclude_upd,
        'pue_enable_logging' => (int)$enable_log,
        'pue_log_retention' => (int)$retention,
    ]);

    wp_send_json_success([ 'ok' => true ]);
}

// AJAX: save Branding
add_action('wp_ajax_pue_save_branding', 'pue_ajax_save_branding');
function pue_ajax_save_branding() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error([ 'msg' => 'forbidden' ], 403);
    }
    if (!isset($_POST['pue_save_branding_nonce']) || !wp_verify_nonce($_POST['pue_save_branding_nonce'], 'pue_save_branding')) {
        wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
    }
    $header = isset($_POST['pue_header_text']) ? pue_sanitize_subject($_POST['pue_header_text']) : '';
    $bg     = isset($_POST['pue_header_bg_color']) ? pue_sanitize_hex($_POST['pue_header_bg_color']) : '#0073aa';
    $footer = isset($_POST['pue_footer_text']) ? pue_sanitize_subject($_POST['pue_footer_text']) : '';
    pue_save_branding_group([
        'pue_header_text' => $header,
        'pue_header_bg_color' => $bg,
        'pue_footer_text' => $footer,
    ]);
    wp_send_json_success([ 'ok' => true ]);
}

// AJAX: save Email Identity
add_action('wp_ajax_pue_save_identity', 'pue_ajax_save_identity');
function pue_ajax_save_identity() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error([ 'msg' => 'forbidden' ], 403);
    }
    if (!isset($_POST['pue_save_identity_nonce']) || !wp_verify_nonce($_POST['pue_save_identity_nonce'], 'pue_save_identity')) {
        wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
    }
    $from_name = isset($_POST['pue_from_name']) ? pue_sanitize_subject($_POST['pue_from_name']) : '';
    $from_email = isset($_POST['pue_from_email']) ? pue_sanitize_email($_POST['pue_from_email']) : '';
    $reply_email = isset($_POST['pue_reply_to_email']) ? pue_sanitize_email($_POST['pue_reply_to_email']) : '';
    pue_save_identity_group([
        'pue_from_name' => $from_name,
        'pue_from_email' => $from_email,
        'pue_reply_to_email' => $reply_email,
    ]);
    wp_send_json_success([ 'ok' => true ]);

// Centralized group saves to keep sanitizer parity with Settings API
}

function pue_save_notifications_group($data) {
    if (!is_array($data)) return;
    // data should already be sanitized by pue_sanitize_* before calling
    update_option('pue_roles', isset($data['pue_roles']) ? (array) $data['pue_roles'] : [], false);
    update_option('pue_subject', isset($data['pue_subject']) ? (string) $data['pue_subject'] : '', false);
    update_option('pue_message', isset($data['pue_message']) ? (string) $data['pue_message'] : '', false);
    update_option('pue_post_types', isset($data['pue_post_types']) ? (array) $data['pue_post_types'] : [], false);
    update_option('pue_exclude_updater', isset($data['pue_exclude_updater']) ? (int) $data['pue_exclude_updater'] : 0, false);
    update_option('pue_enable_logging', isset($data['pue_enable_logging']) ? (int) $data['pue_enable_logging'] : 0, false);
    update_option('pue_log_retention', isset($data['pue_log_retention']) ? (int) $data['pue_log_retention'] : 50, false);
}

function pue_save_branding_group($data) {
    if (!is_array($data)) return;
    update_option('pue_header_text', isset($data['pue_header_text']) ? (string) $data['pue_header_text'] : '', false);
    update_option('pue_header_bg_color', isset($data['pue_header_bg_color']) ? (string) $data['pue_header_bg_color'] : '#0073aa', false);
    update_option('pue_footer_text', isset($data['pue_footer_text']) ? (string) $data['pue_footer_text'] : '', false);
}

function pue_save_identity_group($data) {
    if (!is_array($data)) return;
    update_option('pue_from_name', isset($data['pue_from_name']) ? (string) $data['pue_from_name'] : '', false);
    update_option('pue_from_email', isset($data['pue_from_email']) ? (string) $data['pue_from_email'] : '', false);
    update_option('pue_reply_to_email', isset($data['pue_reply_to_email']) ? (string) $data['pue_reply_to_email'] : '', false);
}

// ---------- 6. HTML Email Template ----------
// Legacy pue_email_template() removed in 1.2.4; use pue_email_template_branded().


// ---------- 7. Sanitize Callbacks ----------
function pue_sanitize_roles($input) {
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

function pue_sanitize_subject($input) {
    if ($input === null) { return ''; }
    if (!is_scalar($input)) { return ''; }
    return sanitize_text_field((string) $input);
}

function pue_sanitize_message($input) {
    if ($input === null) { return ''; }
    if (!is_string($input)) { $input = (string) $input; }
    return wp_kses_post($input);
}

function pue_sanitize_post_types($input) {
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

function pue_sanitize_bool($input) {
    return $input ? 1 : 0;
}

function pue_sanitize_retention($input) {
    $allowed = [50, 200, 1000];
    $val = (int) $input;
    return in_array($val, $allowed, true) ? $val : 50;
}

function pue_sanitize_hex($color) {
    $color = is_string($color) ? $color : '';
    $c = sanitize_hex_color($color);
    return $c ? $c : '#0073aa';
}

function pue_sanitize_email($input) {
    if ($input === null) return '';
    if (!is_string($input)) $input = (string) $input;
    return sanitize_email($input);
}

// Delivery mode sanitizer removed in 1.3.0 (individual-only delivery)

// ---------- 8. Logging ----------
function pue_log_event($type, $sent, $recipients, $subject, $post_id = 0, $extra = []) {
    $enabled = (int) get_option('pue_enable_logging', 0);
    if (!$enabled) return;
    $entry = [
        'ts'         => current_time('timestamp'),
        'type'       => $type,
        'sent'       => $sent ? 1 : 0,
        'recipients' => is_array($recipients) ? array_values($recipients) : [(string) $recipients],
        'subject'    => (string) $subject,
        'post_id'    => (int) $post_id,
    ];
    if (isset($extra['post_title'])) { $entry['post_title'] = (string) $extra['post_title']; }
    if (isset($extra['post_type'])) { $entry['post_type'] = (string) $extra['post_type']; }
    if (isset($extra['editor_id'])) { $entry['editor_id'] = (int) $extra['editor_id']; }
    if (isset($extra['error'])) { $entry['error'] = (string) $extra['error']; }
    if (isset($extra['error_code'])) { $entry['error_code'] = (string) $extra['error_code']; }
    $logs = get_option('pue_logs', []);
    if (!is_array($logs)) { $logs = []; }
    $logs[] = $entry;
    $retention = (int) get_option('pue_log_retention', 50);
    if (!in_array($retention, [50, 200, 1000], true)) { $retention = 50; }
    if (count($logs) > $retention) {
        $logs = array_slice($logs, -$retention);
    }
    update_option('pue_logs', $logs, false);
}

// Capture failure details from wp_mail for plugin emails only (identified via X-PUE header)
add_action('wp_mail_failed', 'pue_wp_mail_failed');
function pue_wp_mail_failed($wp_error) {
    if (!is_wp_error($wp_error)) return;
    $data = $wp_error->get_error_data();
    if (!is_array($data)) return;
    $headers = isset($data['headers']) ? $data['headers'] : [];
    $header_lines = [];
    if (is_string($headers)) {
        $header_lines = preg_split('/\r\n|\r|\n/', $headers);
    } elseif (is_array($headers)) {
        $header_lines = $headers;
    }
    $is_plugin_mail = false;
    $type = 'update';
    $post_id = 0;
    foreach ($header_lines as $line) {
        if (stripos($line, 'X-PUE:') === 0) { $is_plugin_mail = true; }
        if (stripos($line, 'X-PUE-Type:') === 0) {
            $parts = explode(':', $line, 2);
            if (isset($parts[1])) { $type = trim($parts[1]); }
        }
        if (stripos($line, 'X-PUE-Post:') === 0) {
            $parts = explode(':', $line, 2);
            if (isset($parts[1])) { $post_id = (int) trim($parts[1]); }
        }
    }
    if (!$is_plugin_mail) return;
    $to = isset($data['to']) ? $data['to'] : [];
    $recips = is_array($to) ? $to : (empty($to) ? [] : [$to]);
    $subject = isset($data['subject']) ? $data['subject'] : '';
    $extra = [
        'post_title' => $post_id ? get_the_title($post_id) : '',
        'post_type'  => $post_id ? get_post_type($post_id) : '',
        'editor_id'  => get_current_user_id(),
        'error'      => $wp_error->get_error_message(),
        'error_code' => $wp_error->get_error_code(),
    ];
    pue_log_event($type, false, $recips, $subject, $post_id, $extra);
}

// ---------- 9. Helpers ----------
/**
 * Build identity headers (From, Reply-To) from plugin options with placeholder support.
 *
 * @param array   $headers Existing headers array.
 * @param array   $placeholders Map of placeholders for From Name (e.g., [site_name], [editor_name], [year]).
 * @param WP_User $user Current user context (may be null in some flows).
 * @return array Headers with identity lines appended when valid.
 */
function pue_build_identity_headers($headers, $placeholders, $user) {
    $id_from_name  = get_option('pue_from_name', '');
    $id_from_email = sanitize_email(get_option('pue_from_email', ''));
    $id_reply_to   = sanitize_email(get_option('pue_reply_to_email', ''));
    if (!empty($id_from_name) || !empty($id_from_email)) {
        $from_name = $id_from_name ? strtr($id_from_name, is_array($placeholders) ? $placeholders : []) : '';
        $from_name = $from_name ? wp_specialchars_decode($from_name, ENT_QUOTES) : '';
        if ($from_name) { $from_name = str_replace(["\r","\n"], '', $from_name); }
        if ($id_from_email && is_email($id_from_email)) {
            $headers[] = 'From: ' . ($from_name ? $from_name . ' ' : '') . '<' . $id_from_email . '>';
        }
    }
    if ($id_reply_to && is_email($id_reply_to)) {
        $headers[] = 'Reply-To: ' . $id_reply_to;
    }
    return $headers;
}
// Attach AltBody for our plugin emails, when present
add_action('phpmailer_init', 'pue_phpmailer_set_altbody');
function pue_phpmailer_set_altbody($phpmailer) {
    if (!empty($GLOBALS['pue_altbody'])) {
        $phpmailer->AltBody = $GLOBALS['pue_altbody'];
        $GLOBALS['pue_altbody'] = null;
    }
}
/**
 * Compose an email (subject, HTML body, headers) with shared logic for update/test.
 * Ensures placeholder rendering, message normalization, branded wrapping, identity headers,
 * optional plaintext AltBody, and header filters are applied consistently.
 *
 * @param string  $context      'update' or 'test'.
 * @param string  $subject_tmpl Subject template with placeholders.
 * @param string  $message_tmpl Message template (HTML/plain) with placeholders.
 * @param array   $maps         ['subject'=>[], 'message'=>[], 'brand'=>[]] maps. If 'brand' missing, uses union of subject+message maps.
 * @param array   $meta         Extra context: ['post_id'=>int, 'post_after'=>WP_Post|null, 'post_before'=>WP_Post|null].
 * @param WP_User $user         Current user context for filters.
 * @return array                [ $subject, $body_html, $headers ]
 */
function pue_compose_email($context, $subject_tmpl, $message_tmpl, $maps, $meta, $user) {
    $post_id    = isset($meta['post_id']) ? (int) $meta['post_id'] : 0;
    $post_after = isset($meta['post_after']) ? $meta['post_after'] : null;
    $post_before= isset($meta['post_before']) ? $meta['post_before'] : null;

    $subject_map = isset($maps['subject']) && is_array($maps['subject']) ? $maps['subject'] : [];
    $message_map = isset($maps['message']) && is_array($maps['message']) ? $maps['message'] : [];
    $brand_map   = isset($maps['brand'])   && is_array($maps['brand'])   ? $maps['brand']   : array_merge($message_map, $subject_map);

    // Render subject + message
    $subject = strtr((string) $subject_tmpl, $subject_map);
    $subject = apply_filters('pue_email_subject', $subject, $post_id, $post_after, $post_before, $user, $subject_map);

    $message = strtr((string) $message_tmpl, $message_map);
    $message = apply_filters('pue_email_message', $message, $post_id, $post_after, $post_before, $user, $message_map);
    $message = function_exists('pue_normalize_message_html') ? pue_normalize_message_html($message) : $message;

    // Wrap with branded template
    $body_html = pue_email_template_branded($message, $brand_map);
    $body_html = apply_filters('pue_email_template_html', $body_html, $message, $post_id, $post_after, $post_before, $user);

    // Base + identity headers
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'X-PUE: 1',
        'X-PUE-Type: ' . ($context === 'update' ? 'update' : 'test'),
        'X-PUE-Post: ' . $post_id,
    ];
    $headers = pue_build_identity_headers($headers, $brand_map, $user);
    $headers = apply_filters('pue_email_headers', $headers, $post_id, $post_after, $post_before, $user);

    // Optional plaintext alternative
    if (apply_filters('pue_email_plaintext_enabled', false, $body_html, $post_id, $context)) {
        $width = (int) apply_filters('pue_email_plaintext_width', 78, $post_id, $context);
        $alt = pue_generate_plaintext($body_html, $width);
        $alt = apply_filters('pue_email_plaintext', $alt, $body_html, $post_id, $context);
        $GLOBALS['pue_altbody'] = $alt;
    }

    return [ $subject, $body_html, $headers ];
}

/**
 * Build placeholder maps for a test (no post context).
 * Returns ['subject'=>[], 'message'=>[], 'brand'=>[]].
 */
function pue_build_placeholder_maps_test($user) {
    $site_name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $now_str     = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp'));
    $editor_name = $user ? $user->display_name : '';
    $sample_post = __( 'Sample Post', 'hackrepair-post-update-email-notifier' );
    $sample_url  = home_url( '/' );
    $edit_url    = admin_url( 'edit.php' );

    $subject_map = [
        '[post_title]'    => $sample_post,
        '[post_url]'      => $sample_url,
        '[editor_name]'   => $editor_name,
        '[site_name]'     => $site_name,
        '[post_type]'     => __( 'Post', 'hackrepair-post-update-email-notifier' ),
        '[updated_at]'    => $now_str,
        '[author_name]'   => $editor_name,
        '[post_edit_url]' => $edit_url,
        '[year]'          => date('Y'),
    ];
    $message_map = [
        '[post_title]'    => esc_html($sample_post),
        '[post_url]'      => esc_url($sample_url),
        '[editor_name]'   => esc_html($editor_name),
        '[site_name]'     => esc_html(get_bloginfo('name')),
        '[post_type]'     => esc_html(__( 'Post', 'hackrepair-post-update-email-notifier' )),
        '[updated_at]'    => esc_html($now_str),
        '[author_name]'   => esc_html($editor_name),
        '[post_edit_url]' => esc_url($edit_url),
        '[year]'          => esc_html(date('Y')),
    ];
    $brand_map = [
        '[site_name]'  => $site_name,
        '[updated_at]' => $now_str,
        '[editor_name]'=> $editor_name,
        '[year]'       => date('Y'),
    ];
    return [ 'subject' => $subject_map, 'message' => $message_map, 'brand' => $brand_map ];
}

/**
 * Build placeholder maps for an update email context.
 * Returns ['subject'=>[], 'message'=>[], 'brand'=>[]].
 */
function pue_build_placeholder_maps_update($post_ID, $post_after, $post_before, $editor) {
    $post_title  = $post_after ? $post_after->post_title : get_the_title($post_ID);
    $post_url    = get_permalink($post_ID);
    $site_name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $ptype_obj   = $post_after ? get_post_type_object($post_after->post_type) : null;
    $ptype_label = ($ptype_obj && isset($ptype_obj->labels->singular_name)) ? $ptype_obj->labels->singular_name : ($post_after ? $post_after->post_type : get_post_type($post_ID));
    $updated_at  = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp'));
    $author_name = $post_after ? get_the_author_meta('display_name', $post_after->post_author) : '';
    $edit_url    = get_edit_post_link($post_ID);
    if (!$edit_url) {
        $edit_url = admin_url('post.php?post=' . (int) $post_ID . '&action=edit');
    }

    $subject_map = [
        '[post_title]'    => wp_strip_all_tags((string) $post_title),
        '[post_url]'      => (string) $post_url,
        '[editor_name]'   => $editor ? (string) $editor->display_name : '',
        '[site_name]'     => (string) $site_name,
        '[post_type]'     => (string) $ptype_label,
        '[updated_at]'    => (string) $updated_at,
        '[author_name]'   => (string) $author_name,
        '[post_edit_url]' => (string) $edit_url,
        '[year]'          => date('Y'),
    ];

    $message_map = [
        '[post_title]'    => esc_html((string) $post_title),
        '[post_url]'      => esc_url((string) $post_url),
        '[editor_name]'   => esc_html($editor ? (string) $editor->display_name : ''),
        '[site_name]'     => esc_html(get_bloginfo('name')),
        '[post_type]'     => esc_html((string) $ptype_label),
        '[updated_at]'    => esc_html((string) $updated_at),
        '[author_name]'   => esc_html((string) $author_name),
        '[post_edit_url]' => esc_url((string) $edit_url),
        '[year]'          => esc_html(date('Y')),
    ];

    $brand_map = [
        '[site_name]'  => (string) $site_name,
        '[updated_at]' => (string) $updated_at,
        '[editor_name]'=> $editor ? (string) $editor->display_name : '',
        '[year]'       => date('Y'),
    ];

    return [ 'subject' => $subject_map, 'message' => $message_map, 'brand' => $brand_map ];
}
function pue_sanitize_emails($emails) {
    $out = [];
    foreach ((array) $emails as $e) {
        $e = sanitize_email($e);
        if ($e && is_email($e)) { $out[] = $e; }
    }
    return array_values(array_unique($out));
}

function pue_csv_safe($value) {
    if (!is_string($value)) { return $value; }
    $trim = ltrim($value);
    if ($trim !== '' && in_array($trim[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value; // prefix single-quote to prevent CSV injection in spreadsheet apps
    }
    return $value;
}

function pue_generate_plaintext($html, $width = 78) {
    // Convert anchors to "text (URL)"
    $html = preg_replace_callback('/<a\s[^>]*href=[\"\']([^\"\']+)[\"\'][^>]*>(.*?)<\\/a>/is', function($m){
        $text = trim(wp_strip_all_tags($m[2]));
        $url  = trim($m[1]);
        if ($text && $url) { return $text . ' (' . $url . ')'; }
        return $url ?: $text;
    }, $html);
    $text = wp_specialchars_decode(wp_strip_all_tags($html));
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = trim($text);
    if ($width > 20) {
        $text = wordwrap($text, $width);
    }
    return $text;
}

/**
 * Find a related last error message for a summary log entry.
 * Scans the full $logs array for the most recent failure matching type/post within the last hour.
 *
 * @param array  $logs  Full log array from option.
 * @param string $type  'update' or 'test'.
 * @param int    $post_id Post ID (0 for tests).
 * @param int    $since_ts Timestamp of the summary row.
 * @return string Error message (short) or empty string.
 */
function pue_get_related_error($logs, $type, $post_id, $since_ts) {
    if (empty($logs) || !is_array($logs)) return '';
    $latest_ts = 0;
    $msg = '';
    $cut = max(0, (int) $since_ts - HOUR_IN_SECONDS);
    foreach ($logs as $e) {
        if (!empty($e['sent'])) continue; // only failures
        $t = isset($e['type']) ? (string) $e['type'] : '';
        if ($t !== $type) continue;
        $pid = isset($e['post_id']) ? (int) $e['post_id'] : 0;
        if ($pid !== (int) $post_id) continue;
        $ts = isset($e['ts']) ? (int) $e['ts'] : 0;
        if ($ts < $cut) continue;
        if ($ts >= $latest_ts) {
            if (!empty($e['error'])) {
                $latest_ts = $ts;
                $msg = (string) $e['error'];
            }
        }
    }
    // Trim overly long messages for table display
    if ($msg && strlen($msg) > 120) {
        $msg = substr($msg, 0, 117) . '…';
    }
    return $msg;
}

/**
 * Detailed variant: returns short, full, and code for tooltip display.
 */
function pue_get_related_error_detail($logs, $type, $post_id, $since_ts) {
    $out = [ 'short' => '', 'full' => '', 'code' => '' ];
    if (empty($logs) || !is_array($logs)) return $out;
    $latest_ts = 0;
    $cut = max(0, (int) $since_ts - HOUR_IN_SECONDS);
    foreach ($logs as $e) {
        if (!empty($e['sent'])) continue;
        $t = isset($e['type']) ? (string) $e['type'] : '';
        if ($t !== $type) continue;
        $pid = isset($e['post_id']) ? (int) $e['post_id'] : 0;
        if ($pid !== (int) $post_id) continue;
        $ts = isset($e['ts']) ? (int) $e['ts'] : 0;
        if ($ts < $cut) continue;
        if ($ts >= $latest_ts && !empty($e['error'])) {
            $latest_ts = $ts;
            $msg = (string) $e['error'];
            $code = isset($e['error_code']) ? (string) $e['error_code'] : '';
            $full = $code ? ($code . ': ' . $msg) : $msg;
            $short = $full;
            if (strlen($short) > 120) { $short = substr($short, 0, 117) . '…'; }
            $out = [ 'short' => $short, 'full' => $full, 'code' => $code ];
        }
    }
    return $out;
}

// Normalize message body for HTML emails: handle literal \n tokens, plain text bodies, and trim artifacts
function pue_normalize_message_html($message) {
    if ($message === null) { return ''; }
    $message = (string) $message;
    // Convert visible backslash sequences to actual newlines
    $message = str_replace(["\\r\\n", "\\r", "\\n"], "\n", $message);
    // Trim leading/trailing whitespace and newlines
    $message = trim($message);
    // If message is plain text (no HTML tags), convert newlines to <br>
    if (!preg_match('/<\\w+[^>]*>/', $message)) {
        $message = nl2br($message);
    } else {
        // For HTML content, remove any stray visible \n that may remain
        $message = str_replace(['\\n', '\\r'], '', $message);
        // Strip leading/trailing <br> artifacts
        $message = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', $message);
        $message = preg_replace('/(?:<br\s*\/?>\s*)+$/', '', $message);
    }
    return $message;
}

// SMTP active detection (heuristic)
function pue_is_smtp_active() {
    $active = false;
    // 1) Known SMTP plugins active?
    $plugins = (array) get_option('active_plugins', []);
    $network = (array) get_site_option('active_sitewide_plugins', []);
    $slugs = [
        'wp-mail-smtp/wp_mail_smtp.php',
        'post-smtp/postman-smtp.php',
        'post-smtp/post-smtp.php',
        'fluent-smtp/fluent-smtp.php',
        'easy-wp-smtp/easy-wp-smtp.php',
        'smtp-mailer/main.php',
    ];
    foreach ($slugs as $slug) {
        if (in_array($slug, $plugins, true) || isset($network[$slug])) { $active = true; break; }
    }
    // 2) Runtime PHPMailer probe (no send): ask plugins to configure PHPMailer and inspect
    if (!$active) {
        $active = pue_probe_phpmailer_smtp();
    }
    return (bool) apply_filters('pue_smtp_active', $active);
}

function pue_probe_phpmailer_smtp() {
    try {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $php = new PHPMailer\PHPMailer\PHPMailer(true);
        } elseif (class_exists('PHPMailer')) {
            $php = new PHPMailer(true);
        } else {
            // Attempt to load core vendor classes (paths may vary across WP versions)
            if (file_exists(ABSPATH . WPINC . '/PHPMailer/PHPMailer.php')) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $php = new PHPMailer\PHPMailer\PHPMailer(true);
            } elseif (file_exists(ABSPATH . WPINC . '/class-phpmailer.php')) {
                require_once ABSPATH . WPINC . '/class-phpmailer.php';
                $php = new PHPMailer(true);
            } else {
                return false;
            }
        }
        // Let any SMTP plugin configure this PHPMailer instance
        do_action_ref_array('phpmailer_init', [ &$php ]);
        $mailer = isset($php->Mailer) ? strtolower((string) $php->Mailer) : '';
        // SMTP if Mailer is smtp, or Host set, or SMTPAuth explicitly true
        if ($mailer === 'smtp') return true;
        if (!empty($php->Host)) return true;
        if (isset($php->SMTPAuth) && $php->SMTPAuth) return true;
    } catch (\Throwable $e) {
        return false;
    }
    return false;
}
// Show SMTP recommendation notice after repeated failures in the last 24h
add_action('admin_init', 'pue_handle_dismiss_smtp_notice');
function pue_handle_dismiss_smtp_notice() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_GET['pue_dismiss_smtp_notice']) || !isset($_GET['_wpnonce'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'pue_dismiss_smtp_notice')) return;
    update_option('pue_smtp_notice_dismiss_until', current_time('timestamp') + DAY_IN_SECONDS, false);
}

add_action('admin_notices', 'pue_maybe_show_smtp_notice');
function pue_maybe_show_smtp_notice() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    // Only show on our settings page to avoid noise
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id !== 'settings_page_pue-settings') return;

    $dismiss_until = (int) get_option('pue_smtp_notice_dismiss_until', 0);
    if ($dismiss_until && $dismiss_until > current_time('timestamp')) return;

    $logs = get_option('pue_logs', []);
    if (empty($logs) || !is_array($logs)) return;
    $cut = current_time('timestamp') - DAY_IN_SECONDS;
    $fails = 0;
    foreach ($logs as $entry) {
        if (!empty($entry['sent'])) continue;
        $ts = isset($entry['ts']) ? (int) $entry['ts'] : 0;
        if ($ts >= $cut) { $fails++; }
        if ($fails >= 3) break;
    }
    if ($fails < 3) return;

    $url = wp_nonce_url(add_query_arg('pue_dismiss_smtp_notice', '1'), 'pue_dismiss_smtp_notice');
    $install_url = admin_url('plugin-install.php?s=SMTP&tab=search&type=term');
    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Email delivery appears to be failing.', 'hackrepair-post-update-email-notifier') . '</strong> ' . esc_html__('We detected repeated mail failures in the last 24 hours.', 'hackrepair-post-update-email-notifier') . ' ' . sprintf(wp_kses_post(__('Consider installing an SMTP plugin (e.g., <a href="%1$s">WP Mail SMTP</a>, Post SMTP, FluentSMTP) and using a domain‑aligned From address.', 'hackrepair-post-update-email-notifier')), esc_url($install_url)) . '</p><p><a href="' . esc_url($url) . '" class="button">' . esc_html__('Dismiss for 24 hours', 'hackrepair-post-update-email-notifier') . '</a></p></div>';
}

// Branded template (uses options)
function pue_email_template_branded_legacy($content, $placeholders = []) {
    $heading     = get_option('pue_header_text', 'Post Update Notification');
    $bg          = get_option('pue_header_bg_color', '#0073aa');
    $footer_text = get_option('pue_footer_text', 'Thank you for supporting us.');
    $bg          = pue_sanitize_hex($bg);
    if (empty($bg)) { $bg = '#0073aa'; }
    // Defensive: normalize any visible \n tokens that might slip through
    if (is_string($content)) {
        $content = str_replace(["\\r\\n", "\\r", "\\n"], '<br>', $content);
        $content = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', $content);
        $content = preg_replace('/(?:<br\s*\/?>\s*)+$/', '', $content);
    }
    // Allow all known placeholders (including [year]) in header/footer
    if (!empty($placeholders) && is_array($placeholders)) {
        $heading = strtr((string) $heading, $placeholders);
        $footer_text = strtr((string) $footer_text, $placeholders);
    }
    // Always ensure [year] works even if not passed
    $heading = strtr((string) $heading, [ '[year]' => date('Y') ]);
    $footer_text = strtr((string) $footer_text, [ '[year]' => date('Y') ]);
    $heading     = esc_html($heading);
    $footer_out  = esc_html($footer_text);

    return '<div style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding:20px;"><div style="max-width:600px;margin:auto;background:white;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;"><div style="background-color:' . esc_attr($bg) . ';color:white;padding:12px 20px;"><h2 style="margin:0;">' . $heading . '</h2></div><div style="padding:20px;font-size:15px;line-height:1.6;color:#333;">' . $content . '</div><div style="background-color:#f1f1f1;text-align:center;padding:10px;color:#666;font-size:13px;">' . $footer_out . '</div></div></div>';
}

// New wrapper preserving original function name
function pue_email_template_branded($content, $placeholders = []) {
    return pue_email_template_branded_legacy($content, $placeholders);
}
?>
