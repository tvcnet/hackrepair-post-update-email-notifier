<?php
/**
 * Settings-page actions shared by admin-post (no JavaScript) and admin-ajax.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** One handler per action; responds with JSON over AJAX, otherwise stores a notice and redirects. */
final class PUE_Admin_Actions {

	private static $instance = null;

	/**
	 * Action => [method, nonce action, nonce field, settings section].
	 *
	 * @var array
	 */
	const ACTIONS = array(
		'pue_save_notifications' => array( 'save_notifications', 'pue_save_notifications', 'pue_save_notifications_nonce', 'notifications' ),
		'pue_save_branding'      => array( 'save_branding', 'pue_save_branding', 'pue_save_branding_nonce', 'branding' ),
		'pue_save_identity'      => array( 'save_identity', 'pue_save_identity', 'pue_save_identity_nonce', 'identity' ),
		'pue_send_test_email'    => array( 'send_test', 'pue_test_email_action', 'pue_test_email_nonce', 'test-email' ),
		'pue_bulk_test_send'     => array( 'bulk_test', 'pue_bulk_test_action', 'pue_bulk_test_nonce', 'bulk-test' ),
		'pue_clear_log'          => array( 'clear_log', 'pue_clear_log_action', 'pue_clear_log_nonce', 'logging' ),
		'pue_reset_defaults'     => array( 'reset_defaults', 'pue_reset_defaults_action', 'pue_reset_defaults_nonce', 'reset-defaults' ),
		'pue_export_log'         => array( 'export_log', 'pue_export_log_action', 'pue_export_log_nonce', 'logging' ),
		'pue_preview_email'      => array( 'preview', 'pue_admin_read', 'pue_read_nonce', 'test-email' ),
		'pue_count_recipients'   => array( 'count_recipients', 'pue_admin_read', 'pue_read_nonce', 'notifications' ),
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run an action after capability and nonce checks.
	 *
	 * @param string $action Action name.
	 */
	public function handle( $action ) {
		if ( ! isset( self::ACTIONS[ $action ] ) ) {
			return;
		}
		list( $method, $nonce_action, $nonce_field, $section ) = self::ACTIONS[ $action ];
		if ( ! current_user_can( pue_capability() ) ) {
			$this->respond( false, array( 'msg' => 'forbidden' ), __( 'You do not have permission to change these settings.', 'hackrepair-post-update-email-notifier' ), $section, 403 );
		}
		// Older scripts send the test-email nonce as "nonce".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This reads the nonce value for verification.
		$raw   = isset( $_POST[ $nonce_field ] ) ? $_POST[ $nonce_field ] : ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : '' );
		$nonce = sanitize_text_field( wp_unslash( (string) $raw ) );
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			$this->respond( false, array( 'msg' => 'bad_nonce' ), __( 'Your session expired. Please reload the page and try again.', 'hackrepair-post-update-email-notifier' ), $section, 400 );
		}
		$this->$method( $section );
	}

	/**
	 * Finish the request.
	 *
	 * @param bool   $ok Success.
	 * @param array  $data JSON data.
	 * @param string $message Plain-text notice for the redirect path.
	 * @param string $section Section to return to.
	 * @param int    $status HTTP status for JSON errors.
	 * @param string $type Notice type override.
	 */
	private function respond( $ok, $data, $message, $section, $status = 200, $type = '' ) {
		if ( wp_doing_ajax() ) {
			if ( $ok ) {
				wp_send_json_success( $data );
			}
			wp_send_json_error( $data, $status >= 400 ? $status : null );
		}
		pue_set_admin_notice( $type ? $type : ( $ok ? 'success' : 'error' ), $message, $section );
		wp_safe_redirect( pue_settings_url( $section ) );
		exit;
	}

	/**
	 * Raw POST value.
	 *
	 * @param string $key Field.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	private function post( $key, $fallback = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle() before any action method runs.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $fallback;
	}

	private function save_notifications( $section ) {
		pue_save_notifications_group(
			array(
				'pue_roles'            => pue_sanitize_roles( (array) $this->post( 'pue_roles', array() ) ),
				'pue_subject'          => pue_sanitize_subject( $this->post( 'pue_subject', '' ) ),
				'pue_message'          => pue_sanitize_message( $this->post( 'pue_message', '' ) ),
				'pue_post_types'       => pue_sanitize_post_types( (array) $this->post( 'pue_post_types', array() ) ),
				'pue_exclude_updater'  => pue_sanitize_bool( $this->post( 'pue_exclude_updater', 0 ) ),
				'pue_enable_logging'   => pue_sanitize_bool( $this->post( 'pue_enable_logging', 0 ) ),
				'pue_log_retention'    => pue_sanitize_retention( $this->post( 'pue_log_retention', 50 ) ),
				'pue_send_policy'      => PUE_Settings_Manager::instance()->sanitize_send_policy( $this->post( 'pue_send_policy', 'skip' ) ),
				'pue_notify_events'    => PUE_Settings_Manager::instance()->sanitize_notify_events( $this->post( 'pue_notify_events', 'all' ) ),
				'pue_cooldown_minutes' => PUE_Settings_Manager::instance()->sanitize_cooldown( $this->post( 'pue_cooldown_minutes', 0 ) ),
				'pue_delivery_mode'    => PUE_Settings_Manager::instance()->sanitize_delivery_mode( $this->post( 'pue_delivery_mode', 'auto' ) ),
			)
		);
		$this->respond( true, array( 'ok' => true ), __( 'Notification settings saved.', 'hackrepair-post-update-email-notifier' ), $section );
	}

	private function save_branding( $section ) {
		pue_save_branding_group(
			array(
				'pue_header_text'     => pue_sanitize_subject( $this->post( 'pue_header_text', '' ) ),
				'pue_header_bg_color' => pue_sanitize_hex( $this->post( 'pue_header_bg_color', '' ) ),
				'pue_footer_text'     => pue_sanitize_subject( $this->post( 'pue_footer_text', '' ) ),
			)
		);
		$this->respond( true, array( 'ok' => true ), __( 'Branding saved.', 'hackrepair-post-update-email-notifier' ), $section );
	}

	private function save_identity( $section ) {
		pue_save_identity_group(
			array(
				'pue_from_name'      => pue_sanitize_subject( $this->post( 'pue_from_name', '' ) ),
				'pue_from_email'     => pue_sanitize_email( $this->post( 'pue_from_email', '' ) ),
				'pue_reply_to_email' => pue_sanitize_email( $this->post( 'pue_reply_to_email', '' ) ),
			)
		);
		$this->respond( true, array( 'ok' => true ), __( 'Email identity saved.', 'hackrepair-post-update-email-notifier' ), $section );
	}

	private function send_test( $section ) {
		$user = wp_get_current_user();
		$to   = ! empty( $user->user_email ) ? $user->user_email : get_option( 'admin_email' );
		$sent = pue_send_test_email( $to, PUE_Defaults::option( 'pue_subject' ), PUE_Defaults::option( 'pue_message' ) );
		if ( $sent ) {
			/* translators: %s: recipient address. */
			$msg = sprintf( __( 'Email accepted for sending to %s. Acceptance does not confirm delivery.', 'hackrepair-post-update-email-notifier' ), $to );
		} else {
			/* translators: %s: recipient address. */
			$msg = sprintf( __( 'The test email to %s was not accepted for sending. Check your mail setup or use an SMTP plugin for more reliable delivery.', 'hackrepair-post-update-email-notifier' ), $to );
		}
		$this->respond( $sent, array( 'html' => pue_notice_html( $sent ? 'success' : 'error', $msg ) ), $msg, $section );
	}

	private function bulk_test( $section ) {
		$email = sanitize_email( (string) $this->post( 'pue_bulk_email', '' ) );
		if ( ! $email || ! is_email( $email ) ) {
			$msg = __( 'Invalid email address.', 'hackrepair-post-update-email-notifier' );
			$this->respond( false, array( 'error' => $msg ), $msg, $section, 400 );
		}
		$count     = max( 1, min( 200, (int) $this->post( 'pue_bulk_count', 1 ) ) );
		$remaining = pue_bulk_remaining();
		if ( $remaining < 1 ) {
			$msg = __( 'The daily bulk test limit has been reached. Try again tomorrow.', 'hackrepair-post-update-email-notifier' );
			$this->respond( false, array( 'error' => $msg ), $msg, $section, 429 );
		}
		$limited = $count > $remaining;
		$count   = min( $count, $remaining );
		$targets = $this->post( 'pue_bulk_plus' ) ? pue_generate_plus_aliases( $email, $count ) : array_fill( 0, $count, $email );
		$detail  = (bool) $this->post( 'pue_bulk_log_detail' );
		$subject = PUE_Defaults::option( 'pue_subject' );
		$message = PUE_Defaults::option( 'pue_message' );
		$sent    = 0;
		foreach ( $targets as $i => $addr ) {
			if ( pue_send_test_email( $addr, '[Bulk ' . ( $i + 1 ) . '] ' . $subject, $message, $detail ) ) {
				++$sent;
			}
			usleep( 50000 );
		}
		pue_record_bulk_usage( count( $targets ) );
		if ( ! $detail ) {
			pue_log_event(
				'test',
				count( $targets ) === $sent,
				array( $email ),
				'[Bulk Individual Summary] ' . $subject,
				0,
				array(
					'mode'       => 'bulk_individual_summary',
					'sent_count' => $sent,
					'total'      => count( $targets ),
				)
			);
		}
		/* translators: 1: accepted count, 2: total count. */
		$msg = sprintf( __( 'Bulk test complete. Accepted for sending: %1$d of %2$d. Acceptance does not confirm delivery.', 'hackrepair-post-update-email-notifier' ), $sent, count( $targets ) );
		if ( $limited ) {
			$msg .= ' ' . __( 'The count was reduced to stay within the daily bulk test limit.', 'hackrepair-post-update-email-notifier' );
		}
		$type = ( $sent < count( $targets ) || $limited ) ? 'warning' : 'success';
		$this->respond(
			true,
			array(
				'html'  => pue_notice_html( $type, $msg ),
				'sent'  => $sent,
				'total' => count( $targets ),
				'mode'  => 'individual',
			),
			$msg,
			$section,
			200,
			$type
		);
	}

	private function clear_log( $section ) {
		delete_option( 'pue_logs' );
		add_option( 'pue_logs', array(), '', 'no' );
		$this->respond( true, array( 'ok' => true ), __( 'Log cleared.', 'hackrepair-post-update-email-notifier' ), $section );
	}

	private function reset_defaults( $section ) {
		foreach ( PUE_Defaults::stored_keys() as $opt ) {
			if ( 'pue_bulk_usage' !== $opt ) {
				delete_option( $opt );
			}
		}
		$this->respond( true, array( 'ok' => true ), __( 'All settings have been restored to their defaults.', 'hackrepair-post-update-email-notifier' ), $section );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Shared action signature.
	private function export_log( $section ) {
		PUE_Logger::instance()->export_csv_and_exit();
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Shared action signature.
	private function preview( $section ) {
		// Render unsaved form values without storing them.
		$overrides = array(
			'pue_header_text'     => pue_sanitize_subject( $this->post( 'pue_header_text', PUE_Defaults::option( 'pue_header_text' ) ) ),
			'pue_header_bg_color' => pue_sanitize_hex( $this->post( 'pue_header_bg_color', PUE_Defaults::option( 'pue_header_bg_color' ) ) ),
			'pue_footer_text'     => pue_sanitize_subject( $this->post( 'pue_footer_text', PUE_Defaults::option( 'pue_footer_text' ) ) ),
		);
		$filters   = array();
		foreach ( $overrides as $opt => $value ) {
			$filters[ $opt ] = static function () use ( $value ) {
				return $value;
			};
			add_filter( 'pre_option_' . $opt, $filters[ $opt ] );
		}
		$user                   = wp_get_current_user();
		$subject                = pue_sanitize_subject( $this->post( 'pue_subject', PUE_Defaults::option( 'pue_subject' ) ) );
		$message                = pue_sanitize_message( $this->post( 'pue_message', PUE_Defaults::option( 'pue_message' ) ) );
		list( $subject, $html ) = pue_compose_email( 'test', $subject, $message, pue_build_placeholder_maps_test( $user ), array( 'post_id' => 0 ), $user );
		foreach ( $filters as $opt => $cb ) {
			remove_filter( 'pre_option_' . $opt, $cb );
		}
		wp_send_json_success(
			array(
				'subject' => $subject,
				'html'    => '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0">' . $html . '</body></html>',
			)
		);
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Shared action signature.
	private function count_recipients( $section ) {
		$roles   = pue_sanitize_roles( (array) $this->post( 'pue_roles', array() ) );
		$user    = wp_get_current_user();
		$exclude = $this->post( 'pue_exclude_updater' ) && ! empty( $user->user_email ) ? $user->user_email : '';
		$count   = count( pue_get_recipients( $roles, $exclude ) );
		wp_send_json_success(
			array(
				'count' => $count,
				'text'  => pue_recipient_count_text( $count ),
			)
		);
	}
}
