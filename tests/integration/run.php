<?php
/**
 * Real-WordPress integration checks. Run with: wp eval-file tests/integration/run.php
 *
 * Mail is captured with pre_wp_mail, so nothing is sent. A disposable fixture post is created and
 * deleted, and every plugin option is restored afterwards.
 */

// wp eval-file runs inside a function, so shared state lives in $GLOBALS.
$GLOBALS['pue_it_fail'] = 0;
$pue_captured           = array();
function pue_it_check( $ok, $label ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		++$GLOBALS['pue_it_fail'];
	}
}
function pue_it_jobs() {
	$ids = array();
	foreach ( (array) _get_cron_array() as $events ) {
		foreach ( (array) ( $events['pue_send_batch'] ?? array() ) as $event ) {
			$job = get_option( 'pue_job_' . $event['args'][0] );
				if ( is_array( $job ) && (int) $job['post_id'] === (int) ( $GLOBALS['pue_it_post_id'] ?? -1 ) ) {
					$ids[] = $event['args'][0];
				}
		}
	}
	return $ids;
}
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) use ( &$pue_captured ) {
		$pue_captured[] = $atts;
		return true;
	},
	10,
	2
);

$pue_backup = array();
foreach ( PUE_Defaults::stored_keys() as $pue_key ) {
	$pue_backup[ $pue_key ] = get_option( $pue_key, '__pue_missing__' );
}
$pue_registered_default = 'skip' === pue_send_policy();
$pue_id = 0;
$pue_future_id = 0;
try {
	$pue_admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	wp_set_current_user( $pue_admin[0]->ID );
	foreach ( array( 'pue_roles' => array( 'administrator' ), 'pue_enable_logging' => 1, 'pue_delivery_mode' => 'sync', 'pue_send_policy' => 'skip', 'pue_notify_events' => 'all', 'pue_cooldown_minutes' => 0, 'pue_exclude_updater' => 0, 'pue_post_types' => array(), 'pue_message' => '<p>[post_title]</p>' ) as $k => $v ) {
		update_option( $k, $v );
	}

	$reg = get_registered_meta_keys( 'post', 'post' );
	pue_it_check( isset( $reg['_pue_do_not_notify'] ) && $pue_registered_default === $reg['_pue_do_not_notify']['default'], 'per-post flag registered with the configured site default' );

	$pue_id = wp_insert_post( array( 'post_title' => 'PUE integration fixture', 'post_status' => 'draft', 'post_content' => 'one two', 'post_type' => 'post' ) );
	$GLOBALS['pue_it_post_id'] = $pue_id;
	pue_it_check( 0 === count( $pue_captured ), 'creating a draft sends nothing' );
	wp_update_post( array( 'ID' => $pue_id, 'post_status' => 'publish' ) );
	pue_it_check( 0 === count( $pue_captured ), 'publishing without an opt-in sends nothing' );

	update_post_meta( $pue_id, '_pue_do_not_notify', 0 );
	wp_update_post( array( 'ID' => $pue_id, 'post_content' => 'one two three four' ) );
	pue_it_check( count( $pue_captured ) >= 1 && false !== strpos( $pue_captured[0]['subject'], '[Post Update Email Notifier]' ), 'explicit opt-in sends through the real wp_after_insert_post path' );
	pue_it_check( ! metadata_exists( 'post', $pue_id, '_pue_do_not_notify' ), 'opt-in consumed after sending' );
	$n = count( $pue_captured );
	wp_update_post( array( 'ID' => $pue_id, 'post_content' => 'again' ) );
	pue_it_check( count( $pue_captured ) === $n, 'the next update is skipped' );

	update_option( 'pue_message', '[change_summary] [revision_url]' );
	update_post_meta( $pue_id, '_pue_do_not_notify', 0 );
	wp_update_post( array( 'ID' => $pue_id, 'post_title' => 'PUE integration fixture renamed' ) );
	$last = end( $pue_captured );
	pue_it_check( false !== strpos( $last['message'], 'Title changed' ), 'WordPress supplies $post_before, producing a change summary' );

	update_option( 'pue_cooldown_minutes', 15 );
	update_post_meta( $pue_id, '_pue_do_not_notify', 0 );
	$n = count( $pue_captured );
	wp_update_post( array( 'ID' => $pue_id, 'post_content' => 'cooldown' ) );
	pue_it_check( count( $pue_captured ) === $n, 'cooldown blocks an immediate repeat' );
	update_option( 'pue_cooldown_minutes', 0 );

	// Exercise WordPress's scheduled-publish callback on a disposable overdue future post.
	$pue_future_id = wp_insert_post( array( 'post_title' => 'PUE scheduled fixture', 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'post_type' => 'post' ) );
	update_post_meta( $pue_future_id, '_pue_do_not_notify', 0 );
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_date' => gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ), array( 'ID' => $pue_future_id ) );
	clean_post_cache( $pue_future_id );
	$n = count( $pue_captured );
	check_and_publish_future_post( $pue_future_id );
	pue_it_check( 'publish' === get_post_status( $pue_future_id ) && count( $pue_captured ) > $n, 'scheduled first publish honors the stored opt-in through the real cron callback' );

	update_option( 'pue_delivery_mode', 'background' );
	update_post_meta( $pue_id, '_pue_do_not_notify', 0 );
	$n = count( $pue_captured );
	wp_update_post( array( 'ID' => $pue_id, 'post_content' => 'background' ) );
	$jobs = pue_it_jobs();
	pue_it_check( count( $pue_captured ) === $n && count( $jobs ) > 0, 'background mode schedules a WP-Cron batch instead of sending during save' );
	for ( $i = 0; $i < 20 && $jobs; $i++ ) {
		foreach ( $jobs as $job ) {
			wp_unschedule_event( (int) wp_next_scheduled( 'pue_send_batch', array( $job ) ), 'pue_send_batch', array( $job ) );
			do_action( 'pue_send_batch', $job );
		}
		$jobs = pue_it_jobs();
	}
	$logs = get_option( 'pue_logs', array() );
	$entry = end( $logs );
	pue_it_check( count( $pue_captured ) > $n && 'background' === ( $entry['mode'] ?? '' ) && 1 === (int) $entry['sent'], 'cron batches send and complete the log entry' );

	// Call our callback directly: other plugins may legitimately filter the full Site Health list.
	$tests = PUE_Site_Health::register( array( 'direct' => array(), 'async' => array() ) );
	pue_it_check( false !== has_filter( 'site_status_tests', array( 'PUE_Site_Health', 'register' ) ) && isset( $tests['direct']['pue_mail_failures'], $tests['direct']['pue_from_alignment'] ), 'Site Health tests registered' );
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
	pue_it_check( isset( $exporters['hackrepair-post-update-email-notifier'] ), 'privacy exporter registered' );
	pue_it_check( has_action( 'admin_post_pue_save_branding' ) && has_action( 'wp_ajax_pue_save_branding' ), 'branding saves are routed for admin-post and AJAX' );
} finally {
	if ( $pue_future_id ) {
		wp_clear_scheduled_hook( 'publish_future_post', array( $pue_future_id ) );
		wp_delete_post( $pue_future_id, true );
	}
	if ( $pue_id ) {
		wp_delete_post( $pue_id, true );
	}
	foreach ( $pue_backup as $pue_key => $pue_value ) {
		if ( '__pue_missing__' === $pue_value ) {
			delete_option( $pue_key );
		} else {
			update_option( $pue_key, $pue_value );
		}
	}
	foreach ( pue_it_jobs() as $job ) {
		wp_clear_scheduled_hook( 'pue_send_batch', array( $job ) );
		delete_option( 'pue_job_' . $job );
	}
}
if ( $GLOBALS['pue_it_fail'] ) {
	WP_CLI::error( $GLOBALS['pue_it_fail'] . ' integration check(s) failed.' );
}
WP_CLI::success( 'Integration checks passed. Options restored and fixture deleted.' );
