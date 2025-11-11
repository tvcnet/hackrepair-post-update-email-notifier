<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Logger')) {
    class PUE_Logger {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function is_enabled() {
            return (int) get_option('pue_enable_logging', 0) === 1;
        }

        public function retention() {
            $retention = (int) get_option('pue_log_retention', 50);
            return in_array($retention, [50, 200, 1000], true) ? $retention : 50;
        }

        public function log_event($type, $sent, $recipients, $subject, $post_id = 0, $extra = []) {
            if (!$this->is_enabled()) return;
            $entry = [
                'ts'         => current_time('timestamp'),
                'type'       => (string) $type,
                'sent'       => $sent ? 1 : 0,
                'recipients' => is_array($recipients) ? array_values($recipients) : [(string) $recipients],
                'subject'    => (string) $subject,
                'post_id'    => (int) $post_id,
            ];
            foreach (['post_title','post_type','editor_id','error','error_code','mode','sent_count','total'] as $k) {
                if (isset($extra[$k])) { $entry[$k] = $extra[$k]; }
            }
            $logs = get_option('pue_logs', []);
            if (!is_array($logs)) { $logs = []; }
            $logs[] = $entry;
            $retention = $this->retention();
            if (count($logs) > $retention) {
                $logs = array_slice($logs, -$retention);
            }
            update_option('pue_logs', $logs, false);
        }

        /**
         * Capture wp_mail_failed for this plugin's emails (identified by X-PUE headers)
         */
        public function capture_mail_failure($wp_error) {
            if (!is_wp_error($wp_error)) return;
            $data = $wp_error->get_error_data();
            if (!is_array($data)) return;
            $headers = isset($data['headers']) ? $data['headers'] : [];
            $header_lines = is_string($headers)
                ? preg_split('/\r\n|\r|\n/', $headers)
                : (is_array($headers) ? $headers : []);

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
            $this->log_event($type, false, $recips, $subject, $post_id, $extra);
        }

        public function export_csv_and_exit() {
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
                $title = $this->csv_safe(isset($entry['post_title']) ? (string) $entry['post_title'] : '');
                $ptype = $this->csv_safe(isset($entry['post_type']) ? (string) $entry['post_type'] : '');
                $editor_id = isset($entry['editor_id']) ? (int) $entry['editor_id'] : 0;
                $recips = isset($entry['recipients']) && is_array($entry['recipients']) ? implode('; ', array_map([$this,'csv_safe'], $entry['recipients'])) : '';
                $subject_line = $this->csv_safe(isset($entry['subject']) ? (string) $entry['subject'] : '');
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

        public function csv_safe($value) {
            if (!is_string($value)) { return $value; }
            $trim = ltrim($value);
            if ($trim !== '' && in_array($trim[0], ['=', '+', '-', '@'], true)) {
                return "'" . $value;
            }
            return $value;
        }
    }
}

