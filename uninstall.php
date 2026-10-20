<?php
/**
 * Remove plugin options, post meta, scheduled sends and transients.
 *
 * @package PostUpdateEmailNotifier
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Delete this plugin's data for the current site. */
function pue_uninstall_site() {
	global $wpdb;
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
		'pue_send_policy',
		'pue_notify_events',
		'pue_cooldown_minutes',
		'pue_delivery_mode',
		'pue_bulk_usage',
	);
	foreach ( $option_keys as $key ) {
		delete_option( $key );
	}
	delete_post_meta_by_key( '_pue_do_not_notify' );
	delete_post_meta_by_key( '_pue_last_sent' );
	delete_transient( 'pue_smtp_probe' );
	if ( function_exists( 'wp_unschedule_hook' ) ) {
		wp_unschedule_hook( 'pue_send_batch' );
		wp_unschedule_hook( 'pue_recover_jobs' );
	}
	// Queued background jobs and their locks.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall cleanup of this plugin's own rows.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'pue_job_' ) . '%' ) );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		pue_uninstall_site();
		restore_current_blog();
	}
} else {
	pue_uninstall_site();
}

// Remove only updater status belonging to this plugin.
delete_site_transient( 'pue_github_status' );
delete_site_transient( 'pue_github_limit' );
delete_site_transient( 'pue_manifest_hackrepair-post-update-email-notifier' );
delete_option( 'external_updates-hackrepair-post-update-email-notifier' );
