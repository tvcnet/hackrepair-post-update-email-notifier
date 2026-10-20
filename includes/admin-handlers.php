<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 5. Send Test Email ----------
function pue_send_test_email( $to, $subject, $message, $log = true ) {
	if ( class_exists( 'PUE_Notification_Service' ) ) {
		return PUE_Notification_Service::instance()->send_test_email( $to, $subject, $message, $log );
	}
	return false;
}

// AJAX handler for Test Email (admin)
add_action( 'wp_ajax_pue_send_test_email', 'pue_ajax_send_test_email' );
function pue_ajax_send_test_email() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_send_test_email();
	}
}

// AJAX: Force update check (stay on page)
add_action( 'wp_ajax_pue_force_check_now', 'pue_ajax_force_check_now' );
function pue_ajax_force_check_now() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_force_check_now();
	}
}

// AJAX: Bulk Test Email
add_action( 'wp_ajax_pue_bulk_test_send', 'pue_ajax_bulk_test_send' );
function pue_ajax_bulk_test_send() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_bulk_test_send();
	}
}

/**
 * Generate plus-aliases for a base email. Example: name@example.com => name+1@example.com ... name+N@example.com
 */
function pue_generate_plus_aliases( $email, $count ) {
	$email = sanitize_email( $email );
	$count = (int) $count;
	if ( $count < 1 ) {
		$count = 1;
	} if ( $count > 500 ) {
		$count = 500;
	}
	$parts = explode( '@', $email );
	if ( count( $parts ) !== 2 ) {
		return array_fill( 0, $count, $email ); }
	list($local, $domain) = $parts;
	// Strip existing +tag if present
	$base     = $local;
	$plus_pos = strpos( $local, '+' );
	if ( false !== $plus_pos ) {
		$base = substr( $local, 0, $plus_pos ); }
	$out = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$alias = $base . '+' . $i . '@' . $domain;
		$alias = sanitize_email( $alias );
		if ( $alias ) {
			$out[] = $alias; }
	}
	return $out ? $out : array_fill( 0, $count, $email );
}

// AJAX: save Notifications
add_action( 'wp_ajax_pue_save_notifications', 'pue_ajax_save_notifications' );
function pue_ajax_save_notifications() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_save_notifications();
	}
}

// AJAX: save Branding
add_action( 'wp_ajax_pue_save_branding', 'pue_ajax_save_branding' );
function pue_ajax_save_branding() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_save_branding();
	}
}

// AJAX: save Email Identity
add_action( 'wp_ajax_pue_save_identity', 'pue_ajax_save_identity' );
function pue_ajax_save_identity() {
	if ( class_exists( 'PUE_Admin_Interface' ) ) {
		return PUE_Admin_Interface::instance()->ajax_save_identity();
	}
}

function pue_save_notifications_group( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	// data should already be sanitized by pue_sanitize_* before calling
	update_option( 'pue_roles', isset( $data['pue_roles'] ) ? (array) $data['pue_roles'] : array(), false );
	update_option( 'pue_subject', isset( $data['pue_subject'] ) ? (string) $data['pue_subject'] : '', false );
	update_option( 'pue_message', isset( $data['pue_message'] ) ? (string) $data['pue_message'] : '', false );
	update_option( 'pue_post_types', isset( $data['pue_post_types'] ) ? (array) $data['pue_post_types'] : array(), false );
	update_option( 'pue_exclude_updater', isset( $data['pue_exclude_updater'] ) ? (int) $data['pue_exclude_updater'] : 0, false );
	update_option( 'pue_enable_logging', isset( $data['pue_enable_logging'] ) ? (int) $data['pue_enable_logging'] : 0, false );
	update_option( 'pue_log_retention', isset( $data['pue_log_retention'] ) ? (int) $data['pue_log_retention'] : 50, false );
}

function pue_save_branding_group( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	update_option( 'pue_header_text', isset( $data['pue_header_text'] ) ? (string) $data['pue_header_text'] : '', false );
	update_option( 'pue_header_bg_color', isset( $data['pue_header_bg_color'] ) ? (string) $data['pue_header_bg_color'] : '#0073aa', false );
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

// ---------- 6. HTML Email Template ----------
// Legacy pue_email_template() removed in 1.2.4; use pue_email_template_branded().

/** Export before WordPress renders the settings page or sends HTML headers. */
function pue_handle_export_log() {
	if ( ! isset( $_POST['pue_export_log'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'hackrepair-post-update-email-notifier' ) );
	}
	check_admin_referer( 'pue_export_log_action', 'pue_export_log_nonce' );
	PUE_Logger::instance()->export_csv_and_exit();
}
add_action( 'load-settings_page_pue-settings', 'pue_handle_export_log' );
