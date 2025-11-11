<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Email_Composer')) {
    class PUE_Email_Composer {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function compose($context, $subject_tmpl, $message_tmpl, $maps, $meta, $user) {
            $post_id     = isset($meta['post_id']) ? (int) $meta['post_id'] : 0;
            $post_after  = isset($meta['post_after']) ? $meta['post_after'] : null;
            $post_before = isset($meta['post_before']) ? $meta['post_before'] : null;

            $subject_map = isset($maps['subject']) && is_array($maps['subject']) ? $maps['subject'] : [];
            $message_map = isset($maps['message']) && is_array($maps['message']) ? $maps['message'] : [];
            $brand_map   = isset($maps['brand'])   && is_array($maps['brand'])   ? $maps['brand']   : array_merge($message_map, $subject_map);

            // Render subject + message and apply filters
            $subject = strtr((string) $subject_tmpl, $subject_map);
            $subject = apply_filters('pue_email_subject', $subject, $post_id, $post_after, $post_before, $user, $subject_map);

            $message = strtr((string) $message_tmpl, $message_map);
            $message = apply_filters('pue_email_message', $message, $post_id, $post_after, $post_before, $user, $message_map);
            if (function_exists('pue_normalize_message_html')) {
                $message = pue_normalize_message_html($message);
            }

            // Wrap with branded template (back-compat function)
            if (function_exists('pue_email_template_branded')) {
                $body_html = pue_email_template_branded($message, $brand_map);
            } else {
                $body_html = $message;
            }
            $body_html = apply_filters('pue_email_template_html', $body_html, $message, $post_id, $post_after, $post_before, $user);

            // Base + identity headers
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'X-PUE: 1',
                'X-PUE-Type: ' . ($context === 'update' ? 'update' : 'test'),
                'X-PUE-Post: ' . $post_id,
            ];
            if (function_exists('pue_build_identity_headers')) {
                $headers = pue_build_identity_headers($headers, $brand_map, $user);
            }
            $headers = apply_filters('pue_email_headers', $headers, $post_id, $post_after, $post_before, $user);

            // Optional plaintext alternative
            if (apply_filters('pue_email_plaintext_enabled', false, $body_html, $post_id, $context)) {
                $width = (int) apply_filters('pue_email_plaintext_width', 78, $post_id, $context);
                if (function_exists('pue_generate_plaintext')) {
                    $alt = pue_generate_plaintext($body_html, $width);
                } else {
                    $alt = wp_strip_all_tags($body_html);
                }
                $alt = apply_filters('pue_email_plaintext', $alt, $body_html, $post_id, $context);
                $GLOBALS['pue_altbody'] = $alt;
            }

            return [ $subject, $body_html, $headers ];
        }
    }
}

