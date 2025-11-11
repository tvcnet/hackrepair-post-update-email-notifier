<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Admin_Interface')) {
    class PUE_Admin_Interface {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function render_settings_page() {
            $roles = wp_roles()->roles;
            $selected_roles = get_option('pue_roles', []);
            $subject = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
            $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
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
                if (class_exists('PUE_Logger')) {
                    PUE_Logger::instance()->export_csv_and_exit();
                }
                // Fallback to previous inline export if class not found
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
                $message          = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
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
                      <?php if ($enable_logging): ?>
                      <li><a href="#logging">🧾 <?php echo esc_html__('Logging', 'hackrepair-post-update-email-notifier'); ?></a></li>
                      <?php endif; ?>
                      <li><a href="#updates">⬆️ <?php echo esc_html__('Update Status', 'hackrepair-post-update-email-notifier'); ?></a></li>
                      <li><a href="#bulk-test">📦 <?php echo esc_html__('Bulk Test', 'hackrepair-post-update-email-notifier'); ?></a></li>
                      <li><a href="#reset-defaults">↩️ <?php echo esc_html__('Reset', 'hackrepair-post-update-email-notifier'); ?></a></li>
                      <li><a href="#support">❓ <?php echo esc_html__('Support', 'hackrepair-post-update-email-notifier'); ?></a></li>
                    </ul>
                  </aside>
                  <div class="pue-main">
                    <div class="pue-theme">
            <?php if ($settings_updated): ?>
            <div class="pue-notice pue-notice--success"><span class="pue-notice__emoji">✅</span><p><?php echo esc_html__('Settings updated.', 'hackrepair-post-update-email-notifier'); ?></p></div>
            <?php endif; ?>
            
            <h2 id="notifications"><?php echo esc_html__('Notifications', 'hackrepair-post-update-email-notifier'); ?></h2>
            <form id="pue-form-notifications" method="post" action="options.php" class="pue-form">
                    <?php settings_fields('pue_group_notifications'); ?>
                    <?php wp_nonce_field('pue_save_notifications', 'pue_save_notifications_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Select Roles to Notify', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <div class="pue-right-note" style="float:right; width:40%; max-width:520px; padding:12px 14px; margin:0 24px 10px 16px; background:#fffaf2; border:1px solid #f3d19c; border-radius:6px;">
                                    <p style="margin:0 0 8px 0;">
                                        <?php echo wp_kses_post(__('The <strong>Hack Repair Guy’s Post Update Email Notifier</strong> keeps your team in the loop by sending branded HTML updates whenever posts or pages are modified.', 'hackrepair-post-update-email-notifier')); ?>
                                    </p>
                                    <p style="margin:0; color:#6b6b6b;">
                                        <?php echo esc_html__('Tip: Uncheck all roles or post types to pause notifications — they’ll stay off until you turn them back on.', 'hackrepair-post-update-email-notifier'); ?>
                                    </p>
                                </div>
                                <fieldset>
                                <?php foreach ($roles as $role_key => $role): ?>
                                    <label><input type="checkbox" name="pue_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, (array)$selected_roles, true)); ?> /> <?php echo esc_html($role['name']); ?></label><br />
                                <?php endforeach; ?>
                                </fieldset>
                                <p class="pue-helper-note"><strong>ℹ️ <?php echo esc_html__('Tip', 'hackrepair-post-update-email-notifier'); ?>:</strong> <?php echo esc_html__('To pause notifications temporarily, deselect all roles and click Save. You can re-enable notifications anytime by selecting roles again.', 'hackrepair-post-update-email-notifier'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Subject', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <input type="text" name="pue_subject" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
                                <p class="description"><?php echo esc_html__('Placeholders supported: [post_title], [site_name], [editor_name], [updated_at], [post_type], [author_name], [post_edit_url], [year]', 'hackrepair-post-update-email-notifier'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Message', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <?php
                                $settings = [
                                    'textarea_name' => 'pue_message',
                                    'textarea_rows' => 7,
                                    'teeny' => true,
                                    'media_buttons' => false,
                                    'quicktags' => true,
                                ];
                                wp_editor($message, 'pue_message', $settings);
                                ?>
                                <p class="description"><?php echo esc_html__('Placeholders supported: [post_title], [post_url], [site_name], [editor_name], [updated_at], [post_type], [author_name], [post_edit_url], [year]', 'hackrepair-post-update-email-notifier'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Post Types', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <fieldset>
                                <?php foreach ($post_type_objects as $pt): ?>
                                    <label><input type="checkbox" name="pue_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, (array)$selected_post_types, true)); ?> /> <?php echo esc_html($pt->labels->singular_name); ?></label><br />
                                <?php endforeach; ?>
                                </fieldset>
                                <p class="description"><?php echo esc_html__('If none selected, all public post types are allowed (backward compatible).', 'hackrepair-post-update-email-notifier'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Exclude Updating User', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <label><input type="checkbox" name="pue_exclude_updater" value="1" <?php checked($exclude_updater); ?> /> <?php echo esc_html__('Do not email the user who performed the update.', 'hackrepair-post-update-email-notifier'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Enable Logging', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <label><input type="checkbox" name="pue_enable_logging" value="1" <?php checked($enable_logging); ?> /> <?php echo esc_html__('Log recent emails (50/200/1000 entries) with CSV export.', 'hackrepair-post-update-email-notifier'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Log Retention', 'hackrepair-post-update-email-notifier'); ?></th>
                            <td>
                                <select name="pue_log_retention">
                                    <?php foreach ([50,200,1000] as $opt): ?>
                                        <option value="<?php echo (int)$opt; ?>" <?php selected($log_retention, $opt); ?>><?php echo (int)$opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"></th>
                            <td>
                                <?php submit_button(esc_html__('Save Notifications', 'hackrepair-post-update-email-notifier'),'primary','submit', false); ?>
                            </td>
                        </tr>
                    </table>
            </form>

            <h2 id="branding"><?php echo esc_html__('Branding', 'hackrepair-post-update-email-notifier'); ?></h2>
            <form id="pue-form-branding" method="post" class="pue-form">
                <?php wp_nonce_field('pue_save_branding', 'pue_save_branding_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Header Text', 'hackrepair-post-update-email-notifier'); ?></th>
                        <td>
                            <input type="text" name="pue_header_text" value="<?php echo esc_attr(get_option('pue_header_text', '[site_name] Notification')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Header Background Color', 'hackrepair-post-update-email-notifier'); ?></th>
                        <td>
                            <?php $bg_val = get_option('pue_header_bg_color', '#0073aa'); ?>
                            <input type="text" name="pue_header_bg_color" value="<?php echo esc_attr($bg_val); ?>" class="regular-text pue-color-input" />
                            <span class="pue-color-swatch" id="pue-swatch-header-bg" aria-hidden="true" style="background: <?php echo esc_attr($bg_val); ?>;"></span>
                            <p class="description"><?php echo esc_html__('Use a HEX color (e.g., #0073aa).', 'hackrepair-post-update-email-notifier'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Footer Text', 'hackrepair-post-update-email-notifier'); ?></th>
                        <td>
                            <input type="text" name="pue_footer_text" value="<?php echo esc_attr(get_option('pue_footer_text', 'Thank you for supporting [site_name]')); ?>" class="regular-text" />
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
            <form id="pue-form-identity" method="post" class="pue-form">
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
                $slug = dirname(plugin_basename(PUE_MAIN_FILE));
                $manifest_key = 'pue_manifest_' . sanitize_key($slug);
                $manifest = get_site_transient($manifest_key);
                $current_v = defined('PUE_VERSION') ? PUE_VERSION : (string) get_file_data(PUE_MAIN_FILE, ['Version' => 'Version'])['Version'];
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

        public function ajax_send_test_email() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error([ 'html' => '<div class="pue-notice pue-notice--error"><p>' . esc_html__('Permission denied.', 'hackrepair-post-update-email-notifier') . '</p></div>' ], 403);
            }
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            if (!wp_verify_nonce($nonce, 'pue_test_email_action')) {
                wp_send_json_error([ 'html' => '<div class="pue-notice pue-notice--error"><p>' . esc_html__('Security check failed.', 'hackrepair-post-update-email-notifier') . '</p></div>' ], 400);
            }
            $subject = get_option('pue_subject', __('Post Updated: [post_title]', 'hackrepair-post-update-email-notifier'));
            $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));
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

        public function ajax_force_check_now() {
            if (!current_user_can('update_plugins')) {
                wp_send_json_error([ 'msg' => 'forbidden' ], 403);
            }
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            if (!wp_verify_nonce($nonce, 'pue_force_check')) {
                wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
            }
            if (!class_exists('PUE_Drive_Updater')) {
                $main = defined('PUE_MAIN_FILE') ? PUE_MAIN_FILE : __FILE__;
                $path = plugin_dir_path($main) . 'includes/class-pue-drive-updater.php';
                $alt  = dirname(__DIR__) . '/includes/class-pue-drive-updater.php';
                if (file_exists($alt)) require_once $alt;
                elseif (file_exists($path)) require_once $path;
            }
            if (!class_exists('PUE_Drive_Updater')) {
                wp_send_json_error([ 'msg' => 'no_updater' ], 500);
            }
            $manifest = PUE_Drive_Updater::force_refresh();
            if (function_exists('wp_clean_plugins_cache')) { wp_clean_plugins_cache(true); }
            if (function_exists('wp_update_plugins')) { wp_update_plugins(); }
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

        public function ajax_bulk_test_send() {
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
            $message = get_option('pue_message', __('<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier'));

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
                $ok = pue_send_test_email($addr, '[Bulk ' . $i . '] ' . $subject, $message, $log_detail ? true : false);
                if ($ok) { $sent++; } else { $failed++; }
                usleep(50000);
            }
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

        public function ajax_save_notifications() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error([ 'msg' => 'forbidden' ], 403);
            }
            if (!isset($_POST['pue_save_notifications_nonce']) || !wp_verify_nonce($_POST['pue_save_notifications_nonce'], 'pue_save_notifications')) {
                wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
            }
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

        public function ajax_save_branding() {
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

        public function ajax_save_identity() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error([ 'msg' => 'forbidden' ], 403);
            }
            if (!isset($_POST['pue_save_identity_nonce']) || !wp_verify_nonce($_POST['pue_save_identity_nonce'], 'pue_save_identity')) {
                wp_send_json_error([ 'msg' => 'bad_nonce' ], 400);
            }
            $from_name  = isset($_POST['pue_from_name']) ? pue_sanitize_subject($_POST['pue_from_name']) : '';
            $from_email = isset($_POST['pue_from_email']) ? pue_sanitize_email($_POST['pue_from_email']) : '';
            $reply_email= isset($_POST['pue_reply_to_email']) ? pue_sanitize_email($_POST['pue_reply_to_email']) : '';
            pue_save_identity_group([
                'pue_from_name' => $from_name,
                'pue_from_email' => $from_email,
                'pue_reply_to_email' => $reply_email,
            ]);
            wp_send_json_success([ 'ok' => true ]);
        }
    }
}
