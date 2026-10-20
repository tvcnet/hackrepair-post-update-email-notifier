<?php
/**
 * Single source of option defaults.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** Defaults used for settings registration, rendering, sending, reset and uninstall. */
final class PUE_Defaults {

	/**
	 * All option defaults keyed by option name.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'pue_roles'            => array(),
			'pue_subject'          => __( 'Post Updated: [post_title]', 'hackrepair-post-update-email-notifier' ),
			'pue_message'          => __( '<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier' ),
			'pue_post_types'       => array(),
			'pue_exclude_updater'  => 0,
			'pue_enable_logging'   => 0,
			'pue_log_retention'    => 50,
			'pue_header_text'      => __( '[site_name] Notification', 'hackrepair-post-update-email-notifier' ),
			'pue_header_bg_color'  => '#0073aa',
			'pue_footer_text'      => __( 'Thank you for supporting [site_name]', 'hackrepair-post-update-email-notifier' ),
			'pue_from_name'        => '',
			'pue_from_email'       => '',
			'pue_reply_to_email'   => '',
			'pue_send_policy'      => 'skip',
			'pue_notify_events'    => 'all',
			'pue_cooldown_minutes' => 0,
			'pue_delivery_mode'    => 'auto',
		);
	}

	/**
	 * Default for one option.
	 *
	 * @param string $key Option name.
	 * @return mixed|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Stored option value, falling back to the shared default.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public static function option( $key ) {
		return get_option( $key, self::get( $key ) );
	}

	/**
	 * Every option this plugin stores, including runtime state.
	 *
	 * @return string[]
	 */
	public static function stored_keys() {
		return array_merge( array_keys( self::all() ), array( 'pue_logs', 'pue_smtp_notice_dismiss_until', 'pue_bulk_usage' ) );
	}
}
