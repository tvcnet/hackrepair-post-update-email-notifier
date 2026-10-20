<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 0. Load Text Domain and Plugin Links ----------
function pue_load_textdomain() {
	load_plugin_textdomain( 'hackrepair-post-update-email-notifier', false, dirname( plugin_basename( PUE_MAIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'pue_load_textdomain' );

function pue_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=pue-settings' ) ) . '">' . esc_html__( 'Settings', 'hackrepair-post-update-email-notifier' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( PUE_MAIN_FILE ), 'pue_plugin_action_links' );

// ---------- 0.1 Enqueue Admin Assets ----------
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress passes the hook argument; the callback uses current screen instead.
function pue_admin_enqueue_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'settings_page_pue-settings' === $screen->id ) {
		// WP color picker (for branding color input) + our assets
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'wp-color-picker' );
		}
		wp_enqueue_style( 'pue-admin', plugins_url( 'assets/css/admin.css', PUE_MAIN_FILE ), array( 'wp-color-picker' ), PUE_VERSION );
		wp_enqueue_style( 'pue-brand', plugins_url( 'assets/brand/thrg-brand.css', PUE_MAIN_FILE ), array(), PUE_VERSION );
		wp_enqueue_script( 'pue-admin', plugins_url( 'assets/js/admin.js', PUE_MAIN_FILE ), array( 'wp-color-picker' ), PUE_VERSION, true );
		wp_localize_script(
			'pue-admin',
			'PUE_Ajax',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'pue_test_email_action' ),
				'bulkNonce' => wp_create_nonce( 'pue_bulk_test_action' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'pue_admin_enqueue_assets' );
