<?php
/**
 * Text domain, plugin links, admin assets and privacy hooks.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

function pue_load_textdomain() {
	load_plugin_textdomain( 'hackrepair-post-update-email-notifier', false, dirname( plugin_basename( PUE_MAIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'pue_load_textdomain' );

function pue_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( pue_settings_url() ) . '">' . esc_html__( 'Settings', 'hackrepair-post-update-email-notifier' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( PUE_MAIN_FILE ), 'pue_plugin_action_links' );

// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress passes the hook argument; the callback uses current screen instead.
function pue_admin_enqueue_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'settings_page_pue-settings' !== $screen->id ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style( 'pue-admin', plugins_url( 'assets/css/admin.css', PUE_MAIN_FILE ), array( 'wp-color-picker' ), PUE_VERSION );
	wp_enqueue_style( 'pue-brand', plugins_url( 'assets/brand/thrg-brand.css', PUE_MAIN_FILE ), array(), PUE_VERSION );
	wp_enqueue_script( 'pue-help', plugins_url( 'assets/js/help.js', PUE_MAIN_FILE ), array(), PUE_VERSION, true );
	wp_enqueue_script( 'pue-admin', plugins_url( 'assets/js/admin.js', PUE_MAIN_FILE ), array( 'wp-color-picker' ), PUE_VERSION, true );
	wp_localize_script(
		'pue-admin',
		'PUE_Ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'pue_test_email_action' ),
			'i18n'     => array(
				'done'          => __( 'Done!', 'hackrepair-post-update-email-notifier' ),
				'failed'        => __( 'Request failed. Please try again.', 'hackrepair-post-update-email-notifier' ),
				'saveFailed'    => __( 'Save failed. Please try again.', 'hackrepair-post-update-email-notifier' ),
				'expired'       => __( 'Session expired — please refresh.', 'hackrepair-post-update-email-notifier' ),
				'forbidden'     => __( 'Permission denied.', 'hackrepair-post-update-email-notifier' ),
				'notAccepted'   => __( 'Email was not accepted for sending. Review the message and try again.', 'hackrepair-post-update-email-notifier' ),
				'updateDone'    => __( 'Update check complete.', 'hackrepair-post-update-email-notifier' ),
				'updateFailed'  => __( 'Update information is temporarily unavailable. Plugin features continue to work.', 'hackrepair-post-update-email-notifier' ),
				/* translators: 1: message count, 2: address. */
				'bulkConfirm'   => __( 'This sends %1$d test emails to %2$s. Continue?', 'hackrepair-post-update-email-notifier' ),
				'previewFailed' => __( 'The preview could not be created. Please try again.', 'hackrepair-post-update-email-notifier' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'pue_admin_enqueue_assets' );

// WordPress personal data export and erasure for addresses stored in the email log.
add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['hackrepair-post-update-email-notifier'] = array(
			'exporter_friendly_name' => __( 'Post Update Email Notifier log', 'hackrepair-post-update-email-notifier' ),
			'callback'               => function ( $email ) {
				return PUE_Logger::instance()->privacy_export( $email );
			},
		);
		return $exporters;
	}
);
add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['hackrepair-post-update-email-notifier'] = array(
			'eraser_friendly_name' => __( 'Post Update Email Notifier log', 'hackrepair-post-update-email-notifier' ),
			'callback'             => function ( $email ) {
				return PUE_Logger::instance()->privacy_erase( $email );
			},
		);
		return $erasers;
	}
);
