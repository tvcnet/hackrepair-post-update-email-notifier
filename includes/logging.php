<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 8. Logging ----------
function pue_log_event( $type, $sent, $recipients, $subject, $post_id = 0, $extra = array() ) {
	if ( class_exists( 'PUE_Logger' ) ) {
		return PUE_Logger::instance()->log_event( $type, $sent, $recipients, $subject, $post_id, $extra );
	}
	// Fallback to inline logic (pre‑OOP)
	$enabled = (int) get_option( 'pue_enable_logging', 0 );
	if ( ! $enabled ) {
		return;
	}
	$entry = array(
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
		'ts'         => current_time( 'timestamp' ),
		'type'       => $type,
		'sent'       => $sent ? 1 : 0,
		'recipients' => is_array( $recipients ) ? array_values( $recipients ) : array( (string) $recipients ),
		'subject'    => (string) $subject,
		'post_id'    => (int) $post_id,
	);
	foreach ( array( 'post_title', 'post_type', 'editor_id', 'error', 'error_code', 'mode', 'sent_count', 'total' ) as $k ) {
		if ( isset( $extra[ $k ] ) ) {
			$entry[ $k ] = $extra[ $k ]; }
	}
	$logs = get_option( 'pue_logs', array() );
	if ( ! is_array( $logs ) ) {
		$logs = array(); }
	$logs[]    = $entry;
	$retention = (int) get_option( 'pue_log_retention', 50 );
	if ( ! in_array( $retention, array( 50, 200, 1000 ), true ) ) {
		$retention = 50; }
	if ( count( $logs ) > $retention ) {
		$logs = array_slice( $logs, -$retention );
	}
	update_option( 'pue_logs', $logs, false );
}

// Capture failure details from wp_mail for plugin emails only (identified via X-PUE header)
add_action( 'wp_mail_failed', 'pue_wp_mail_failed' );
function pue_wp_mail_failed( $wp_error ) {
	if ( class_exists( 'PUE_Logger' ) ) {
		return PUE_Logger::instance()->capture_mail_failure( $wp_error );
	}
	// Fallback to inline capture
	if ( ! is_wp_error( $wp_error ) ) {
		return;
	}
	$data = $wp_error->get_error_data();
	if ( ! is_array( $data ) ) {
		return;
	}
	$headers        = isset( $data['headers'] ) ? $data['headers'] : array();
	$header_lines   = is_string( $headers )
		? preg_split( '/\r\n|\r|\n/', $headers )
		: ( is_array( $headers ) ? $headers : array() );
	$is_plugin_mail = false;
	$type           = 'update';
	$post_id        = 0;
	foreach ( $header_lines as $line ) {
		if ( stripos( $line, 'X-PUE:' ) === 0 ) {
			$is_plugin_mail = true; }
		if ( stripos( $line, 'X-PUE-Type:' ) === 0 ) {
			$parts = explode( ':', $line, 2 );
			if ( isset( $parts[1] ) ) {
				$type = trim( $parts[1] ); }
		}
		if ( stripos( $line, 'X-PUE-Post:' ) === 0 ) {
			$parts = explode( ':', $line, 2 );
			if ( isset( $parts[1] ) ) {
				$post_id = (int) trim( $parts[1] ); }
		}
	}
	if ( ! $is_plugin_mail ) {
		return;
	}
	$to      = isset( $data['to'] ) ? $data['to'] : array();
	$recips  = is_array( $to ) ? $to : ( empty( $to ) ? array() : array( $to ) );
	$subject = isset( $data['subject'] ) ? $data['subject'] : '';
	$extra   = array(
		'post_title' => $post_id ? get_the_title( $post_id ) : '',
		'post_type'  => $post_id ? get_post_type( $post_id ) : '',
		'editor_id'  => get_current_user_id(),
		'error'      => $wp_error->get_error_message(),
		'error_code' => $wp_error->get_error_code(),
	);
	pue_log_event( $type, false, $recips, $subject, $post_id, $extra );
}
