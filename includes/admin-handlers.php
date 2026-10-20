<?php
/**
 * Admin request routing and compatibility functions.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- Send Test Email ----------
function pue_send_test_email( $to, $subject, $message, $log = true ) {
	return PUE_Notification_Service::instance()->send_test_email( $to, $subject, $message, $log );
}

// Every settings-page action works over admin-ajax (JavaScript) and admin-post (no JavaScript).
foreach ( array_keys( PUE_Admin_Actions::ACTIONS ) as $pue_action ) {
	add_action( 'wp_ajax_' . $pue_action, 'pue_route_admin_action' );
	add_action( 'admin_post_' . $pue_action, 'pue_route_admin_action' );
}
unset( $pue_action );

/** Dispatch the current admin-ajax or admin-post action. */
function pue_route_admin_action() {
	$action = (string) current_action();
	$action = preg_replace( '/^(wp_ajax_|admin_post_)/', '', $action );
	PUE_Admin_Actions::instance()->handle( $action );
}

// Kept for code that calls these functions directly.
function pue_ajax_send_test_email() {
	PUE_Admin_Actions::instance()->handle( 'pue_send_test_email' );
}
function pue_ajax_bulk_test_send() {
	PUE_Admin_Actions::instance()->handle( 'pue_bulk_test_send' );
}
function pue_ajax_save_notifications() {
	PUE_Admin_Actions::instance()->handle( 'pue_save_notifications' );
}
function pue_ajax_save_branding() {
	PUE_Admin_Actions::instance()->handle( 'pue_save_branding' );
}
function pue_ajax_save_identity() {
	PUE_Admin_Actions::instance()->handle( 'pue_save_identity' );
}
function pue_handle_export_log() {
	PUE_Admin_Actions::instance()->handle( 'pue_export_log' );
}

// AJAX: force update check (stay on page).
add_action( 'wp_ajax_pue_force_check_now', 'pue_ajax_force_check_now' );
function pue_ajax_force_check_now() {
	return PUE_Admin_Interface::instance()->ajax_force_check_now();
}

/**
 * Store a one-time notice for the current user, shown after the redirect.
 *
 * @param string $type success|error|warning.
 * @param string $message Plain text.
 * @param string $section Section id.
 */
function pue_set_admin_notice( $type, $message, $section = '' ) {
	set_transient(
		'pue_notice_' . get_current_user_id(),
		array(
			'type'    => $type,
			'message' => (string) $message,
			'section' => (string) $section,
		),
		MINUTE_IN_SECONDS
	);
}

/**
 * Read and clear the current user's notice.
 *
 * @return array|null
 */
function pue_take_admin_notice() {
	$key    = 'pue_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( false === $notice ) {
		return null;
	}
	delete_transient( $key );
	return is_array( $notice ) ? $notice : null;
}

/**
 * Notice markup.
 *
 * @param string $type success|error|warning.
 * @param string $message Plain text.
 * @return string
 */
function pue_notice_html( $type, $message ) {
	$type  = in_array( $type, array( 'success', 'error', 'warning' ), true ) ? $type : 'success';
	$emoji = array(
		'success' => '🎉',
		'error'   => '💥',
		'warning' => '⚠️',
	);
	return '<div class="pue-notice pue-notice--' . esc_attr( $type ) . '" role="status"><span class="pue-notice__emoji" aria-hidden="true">' . $emoji[ $type ] . '</span><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Recipient count sentence for Settings.
 *
 * @param int $count Recipients.
 * @return string
 */
function pue_recipient_count_text( $count ) {
	if ( 0 === (int) $count ) {
		return __( 'No one is currently selected to receive update emails.', 'hackrepair-post-update-email-notifier' );
	}
	/* translators: %d: number of recipients. */
	return sprintf( _n( 'Currently %d person receives update emails (before developer filters).', 'Currently %d people receive update emails (before developer filters).', (int) $count, 'hackrepair-post-update-email-notifier' ), (int) $count );
}

/**
 * Bulk test messages still allowed today.
 *
 * @return int
 */
function pue_bulk_remaining() {
	$cap   = max( 0, (int) apply_filters( 'pue_bulk_daily_cap', 1000 ) );
	$usage = get_option( 'pue_bulk_usage', array() );
	$used  = ( is_array( $usage ) && isset( $usage['day'], $usage['count'] ) && gmdate( 'Y-m-d' ) === $usage['day'] ) ? (int) $usage['count'] : 0;
	return max( 0, $cap - $used );
}

/**
 * Add to today's bulk test usage.
 *
 * @param int $count Messages attempted.
 */
function pue_record_bulk_usage( $count ) {
	$usage = get_option( 'pue_bulk_usage', array() );
	$today = gmdate( 'Y-m-d' );
	$used  = ( is_array( $usage ) && isset( $usage['day'], $usage['count'] ) && $today === $usage['day'] ) ? (int) $usage['count'] : 0;
	update_option(
		'pue_bulk_usage',
		array(
			'day'   => $today,
			'count' => $used + (int) $count,
		),
		false
	);
}

/**
 * Generate plus-aliases for a base email. Example: name@example.com => name+1@example.com ... name+N@example.com
 */
function pue_generate_plus_aliases( $email, $count ) {
	$email = sanitize_email( $email );
	$count = max( 1, min( 500, (int) $count ) );
	$parts = explode( '@', $email );
	if ( count( $parts ) !== 2 ) {
		return array_fill( 0, $count, $email ); }
	list($local, $domain) = $parts;
	$plus_pos             = strpos( $local, '+' );
	$base                 = false !== $plus_pos ? substr( $local, 0, $plus_pos ) : $local;
	$out                  = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$alias = sanitize_email( $base . '+' . $i . '@' . $domain );
		if ( $alias ) {
			$out[] = $alias; }
	}
	return $out ? $out : array_fill( 0, $count, $email );
}

function pue_save_notifications_group( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	// Data should already be sanitized by the pue_sanitize_* callbacks.
	foreach ( array( 'pue_roles', 'pue_post_types' ) as $key ) {
		update_option( $key, isset( $data[ $key ] ) ? (array) $data[ $key ] : array(), false );
	}
	foreach ( array( 'pue_subject', 'pue_message' ) as $key ) {
		update_option( $key, isset( $data[ $key ] ) ? (string) $data[ $key ] : '', false );
	}
	update_option( 'pue_exclude_updater', isset( $data['pue_exclude_updater'] ) ? (int) $data['pue_exclude_updater'] : 0, false );
	update_option( 'pue_enable_logging', isset( $data['pue_enable_logging'] ) ? (int) $data['pue_enable_logging'] : 0, false );
	update_option( 'pue_log_retention', isset( $data['pue_log_retention'] ) ? (int) $data['pue_log_retention'] : 50, false );
	foreach ( array( 'pue_send_policy', 'pue_notify_events', 'pue_cooldown_minutes', 'pue_delivery_mode' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			update_option( $key, $data[ $key ], false );
		}
	}
}

function pue_save_branding_group( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	update_option( 'pue_header_text', isset( $data['pue_header_text'] ) ? (string) $data['pue_header_text'] : '', false );
	update_option( 'pue_header_bg_color', isset( $data['pue_header_bg_color'] ) ? (string) $data['pue_header_bg_color'] : PUE_Defaults::get( 'pue_header_bg_color' ), false );
	update_option( 'pue_footer_text', isset( $data['pue_footer_text'] ) ? (string) $data['pue_footer_text'] : '', false );
}

function pue_save_identity_group( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	update_option( 'pue_from_name', isset( $data['pue_from_name'] ) ? (string) $data['pue_from_name'] : '', false );
	update_option( 'pue_from_email', isset( $data['pue_from_email'] ) ? (string) $data['pue_from_email'] : '', false );
	update_option( 'pue_reply_to_email', isset( $data['pue_reply_to_email'] ) ? (string) $data['pue_reply_to_email'] : '', false );
}
