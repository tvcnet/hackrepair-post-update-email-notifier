<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 1. Register Settings ----------
function pue_register_settings() {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->register_settings();
	}
}
add_action( 'admin_init', 'pue_register_settings' );

// ---------- 2. Create Admin Menu ----------
function pue_create_menu() {
	add_options_page(
		__( 'Post Update Notifier Settings', 'hackrepair-post-update-email-notifier' ),
		__( 'Post Update Notifier', 'hackrepair-post-update-email-notifier' ),
		'manage_options',
		'pue-settings',
		'pue_settings_page_router'
	);
}
add_action( 'admin_menu', 'pue_create_menu' );


function pue_settings_page_router() {
	return PUE_Admin_Interface::instance()->render_settings_page();
}
