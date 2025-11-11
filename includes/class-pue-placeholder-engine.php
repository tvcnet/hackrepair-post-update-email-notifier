<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PUE_Placeholder_Engine')) {
    class PUE_Placeholder_Engine {
        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function build_test_maps($user) {
            $site_name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
            $now_str     = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp'));
            $editor_name = $user ? $user->display_name : '';
            $sample_post = __('Sample Post', 'hackrepair-post-update-email-notifier');
            $sample_url  = home_url('/');
            $edit_url    = admin_url('edit.php');

            $subject_map = [
                '[post_title]'    => $sample_post,
                '[post_url]'      => $sample_url,
                '[editor_name]'   => $editor_name,
                '[site_name]'     => $site_name,
                '[post_type]'     => __('Post', 'hackrepair-post-update-email-notifier'),
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
                '[post_type]'     => esc_html(__('Post', 'hackrepair-post-update-email-notifier')),
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

        public function build_update_maps($post_ID, $post_after, $post_before, $editor) {
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
    }
}

