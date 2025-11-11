<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Notification_Service')) {
    class PUE_Notification_Service {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function handle_post_update($post_ID, $post_after, $post_before) {
            if (wp_is_post_autosave($post_ID) || wp_is_post_revision($post_ID)) return;
            if ($post_after->post_status !== 'publish') return;
            if (!apply_filters('pue_should_notify', true, $post_ID, $post_after, $post_before)) return;

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

            $maps = pue_build_placeholder_maps_update($post_ID, $post_after, $post_before, $editor);
            $maps = apply_filters('pue_placeholders', $maps, $post_ID, $post_after, $post_before, $editor);

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
                do_action('pue_email_sent', $sent, $recipients, $subject, $body_html, $post_ID, $post_after, $post_before, $editor);
            }
            return $post_ID;
        }

        public function send_test_email($to, $subject, $message, $log = true) {
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'X-PUE: 1',
                'X-PUE-Type: test',
                'X-PUE-Post: 0',
            ];
            $user = wp_get_current_user();
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
    }
}
