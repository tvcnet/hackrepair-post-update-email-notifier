<?php
/**
 * Site Health checks for notification delivery.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** Adds mail-related tests to Tools → Site Health. */
final class PUE_Site_Health {

	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register direct tests.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function register( $tests ) {
		$tests['direct']['pue_mail_failures']   = array(
			'label' => __( 'Post update email delivery', 'hackrepair-post-update-email-notifier' ),
			'test'  => array( __CLASS__, 'test_failures' ),
		);
		$tests['direct']['pue_from_alignment']  = array(
			'label' => __( 'Post update email sender address', 'hackrepair-post-update-email-notifier' ),
			'test'  => array( __CLASS__, 'test_from_alignment' ),
		);
		$tests['direct']['pue_background_cron'] = array(
			'label' => __( 'Post update email background sending', 'hackrepair-post-update-email-notifier' ),
			'test'  => array( __CLASS__, 'test_cron' ),
		);
		return $tests;
	}

	/**
	 * Base result.
	 *
	 * @param string $test Test id.
	 * @param string $status good|recommended|critical.
	 * @param string $label Label.
	 * @param string $description Description (plain text).
	 * @return array
	 */
	private static function result( $test, $status, $label, $description ) {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Post Update Email Notifier', 'hackrepair-post-update-email-notifier' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '<p><a href="' . esc_url( pue_settings_url() ) . '">' . esc_html__( 'Open notification settings', 'hackrepair-post-update-email-notifier' ) . '</a></p>',
			'test'        => $test,
		);
	}

	public static function test_failures() {
		if ( ! PUE_Logger::instance()->is_enabled() ) {
			return self::result( 'pue_mail_failures', 'good', __( 'Post update email logging is off', 'hackrepair-post-update-email-notifier' ), __( 'Enable logging in the plugin settings to monitor delivery failures here.', 'hackrepair-post-update-email-notifier' ) );
		}
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Log entries use site-local timestamps.
		$fails = PUE_Logger::instance()->count_failures_since( current_time( 'timestamp' ) - DAY_IN_SECONDS );
		if ( $fails >= 3 ) {
			/* translators: %d: failures in the last 24 hours. */
			return self::result( 'pue_mail_failures', 'recommended', __( 'Post update emails are failing', 'hackrepair-post-update-email-notifier' ), sprintf( __( '%d post update emails failed in the last 24 hours. An SMTP plugin and a domain-aligned From address usually improve delivery.', 'hackrepair-post-update-email-notifier' ), $fails ) );
		}
		return self::result( 'pue_mail_failures', 'good', __( 'No repeated post update email failures', 'hackrepair-post-update-email-notifier' ), __( 'Fewer than three post update emails failed in the last 24 hours. Skipped updates are not counted.', 'hackrepair-post-update-email-notifier' ) );
	}

	public static function test_from_alignment() {
		$from = (string) get_option( 'pue_from_email', '' );
		if ( '' === $from || false === strpos( $from, '@' ) ) {
			return self::result( 'pue_from_alignment', 'good', __( 'Post update emails use the WordPress default sender', 'hackrepair-post-update-email-notifier' ), __( 'No custom From address is set, so WordPress or your SMTP plugin chooses the sender.', 'hackrepair-post-update-email-notifier' ) );
		}
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$from_host = substr( strrchr( $from, '@' ), 1 );
		$site_host = preg_replace( '/^www\./i', '', strtolower( rtrim( $site_host, '.' ) ) );
		$from_host = preg_replace( '/^www\./i', '', strtolower( rtrim( $from_host, '.' ) ) );
		if ( $site_host !== $from_host ) {
			/* translators: 1: From domain, 2: site domain. */
			return self::result( 'pue_from_alignment', 'recommended', __( 'Post update email sender domain differs from the site', 'hackrepair-post-update-email-notifier' ), sprintf( __( 'The From address uses %1$s but the site is %2$s. Review your mail provider configuration. This address comparison does not verify SPF, DKIM, DMARC or delivery.', 'hackrepair-post-update-email-notifier' ), $from_host, $site_host ) );
		}
		return self::result( 'pue_from_alignment', 'good', __( 'Post update email sender matches the site domain', 'hackrepair-post-update-email-notifier' ), __( 'The address domains match (ignoring a www alias). This does not verify SPF, DKIM, DMARC or delivery.', 'hackrepair-post-update-email-notifier' ) );
	}

	public static function test_cron() {
		foreach ( PUE_Delivery::jobs() as $job ) {
			if ( in_array( $job['state'] ?? '', array( 'paused', 'failed' ), true ) ) {
				return self::result( 'pue_background_cron', 'recommended', __( 'Background email needs attention', 'hackrepair-post-update-email-notifier' ), __( 'A queued message could not schedule its next batch. Recovery retries up to five times. Use wp pue jobs to inspect progress and wp pue retry JOB_ID --yes to retry only unattempted recipients.', 'hackrepair-post-update-email-notifier' ) );
			}
		}
		$mode = (string) PUE_Defaults::option( 'pue_delivery_mode' );
		if ( 'sync' !== $mode && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return self::result( 'pue_background_cron', 'recommended', __( 'Background sending depends on a server cron job', 'hackrepair-post-update-email-notifier' ), __( 'WP-Cron is disabled on this site. Make sure a server cron job runs wp-cron.php regularly, or choose “Always send right away”.', 'hackrepair-post-update-email-notifier' ) );
		}
		return self::result( 'pue_background_cron', 'good', __( 'Background sending can run', 'hackrepair-post-update-email-notifier' ), __( 'Large recipient lists are sent in small WP-Cron batches.', 'hackrepair-post-update-email-notifier' ) );
	}
}
