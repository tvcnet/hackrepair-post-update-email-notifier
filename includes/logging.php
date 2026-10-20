<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 8. Logging ----------
function pue_log_event( $type, $sent, $recipients, $subject, $post_id = 0, $extra = array() ) {
	return PUE_Logger::instance()->log_event( $type, $sent, $recipients, $subject, $post_id, $extra );
}

// Capture failure details from wp_mail for plugin emails only (identified via X-PUE header)
add_action( 'wp_mail_failed', 'pue_wp_mail_failed' );
function pue_wp_mail_failed( $wp_error ) {
	return PUE_Logger::instance()->capture_mail_failure( $wp_error );
}
