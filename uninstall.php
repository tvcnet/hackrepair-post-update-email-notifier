<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_keys = array(
    'pue_roles',
    'pue_subject',
    'pue_message',
    'pue_post_types',
    'pue_exclude_updater',
    'pue_enable_logging',
    'pue_logs',
    'pue_log_retention',
    'pue_header_text',
    'pue_header_bg_color',
    'pue_footer_text',
    'pue_from_name',
    'pue_from_email',
    'pue_reply_to_email',
    'pue_smtp_notice_dismiss_until',
);

if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        foreach ( $option_keys as $key ) {
            delete_option( $key );
        }
        restore_current_blog();
    }
} else {
    foreach ( $option_keys as $key ) {
        delete_option( $key );
    }
}
