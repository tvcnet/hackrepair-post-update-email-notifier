<?php
/**
 * Message delivery: immediate sends and WP-Cron background batches.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** Sends one message per recipient, either now or in background batches. */
final class PUE_Delivery {

	const HOOK       = 'pue_send_batch';
	const JOB_PREFIX = 'pue_job_';

	/**
	 * Plaintext alternative for the message currently being sent.
	 *
	 * @var string|null
	 */
	private static $pending_alt = null;

	/** Register delivery hooks. */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_job' ), 10, 1 );
	}

	/**
	 * Attach the pending plaintext part only while this plugin is sending.
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public static function apply_altbody( $phpmailer ) {
		if ( is_string( self::$pending_alt ) && '' !== self::$pending_alt && is_object( $phpmailer ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer defines this public property name.
			$phpmailer->AltBody = self::$pending_alt;
		}
	}

	/**
	 * Send one message and capture its failure detail.
	 *
	 * @param string      $to Recipient.
	 * @param string      $subject Subject.
	 * @param string      $body HTML body.
	 * @param array       $headers Headers.
	 * @param string|null $alt Plaintext alternative.
	 * @return bool
	 */
	public static function send_one( $to, $subject, $body, $headers, $alt = null ) {
		self::$pending_alt = $alt;
		PUE_Logger::instance()->reset_last_error();
		try {
			$ok = wp_mail( $to, $subject, $body, $headers );
		} finally {
			self::$pending_alt = null;
		}
		return (bool) $ok;
	}

	/**
	 * Whether a recipient list should be sent in the background.
	 *
	 * @param int $count Recipient count.
	 * @return bool
	 */
	public static function should_queue( $count ) {
		$mode = (string) PUE_Defaults::option( 'pue_delivery_mode' );
		if ( 'sync' === $mode || ! function_exists( 'wp_schedule_single_event' ) ) {
			return false;
		}
		if ( 'background' === $mode ) {
			return true;
		}
		return $count > (int) apply_filters( 'pue_background_threshold', 20 );
	}

	/**
	 * Send immediately.
	 *
	 * @param array       $recipients Recipients.
	 * @param string      $subject Subject.
	 * @param string      $body Body.
	 * @param array       $headers Headers.
	 * @param string|null $alt Plaintext alternative.
	 * @return array {sent: bool, sent_count: int, total: int, error: string, error_code: string}
	 */
	public static function send_now( $recipients, $subject, $body, $headers, $alt = null ) {
		$result = array(
			'sent'       => true,
			'sent_count' => 0,
			'total'      => count( $recipients ),
			'error'      => '',
			'error_code' => '',
		);
		foreach ( $recipients as $rcpt ) {
			if ( self::send_one( $rcpt, $subject, $body, $headers, $alt ) ) {
				++$result['sent_count'];
				continue;
			}
			$result['sent'] = false;
			$error          = PUE_Logger::instance()->take_last_error();
			if ( $error ) {
				$result['error']      = $error['error'];
				$result['error_code'] = $error['code'];
			}
		}
		return $result;
	}

	/**
	 * Queue a message for background batches.
	 *
	 * @param array $job Job data: post_id, editor_id, subject, body, headers, alt, recipients.
	 * @return string|false Job id.
	 */
	public static function queue( $job ) {
		$id  = strtolower( wp_generate_password( 12, false, false ) );
		$job = array_merge(
			$job,
			array(
				'id'         => $id,
				'offset'     => 0,
				'sent_count' => 0,
				'failed'     => 0,
				'error'      => '',
				'error_code' => '',
				'created'    => time(),
			)
		);
		add_option( self::JOB_PREFIX . $id, $job, '', 'no' );
		$scheduled = wp_schedule_single_event( time(), self::HOOK, array( $id ) );
		if ( false === $scheduled || is_wp_error( $scheduled ) ) {
			delete_option( self::JOB_PREFIX . $id );
			return false;
		}
		return $id;
	}

	/**
	 * Send the next batch of a queued job.
	 *
	 * @param string $id Job id.
	 */
	public static function run_job( $id ) {
		$id  = preg_replace( '/[^a-z0-9]/', '', (string) $id );
		$key = self::JOB_PREFIX . $id;
		$job = get_option( $key );
		if ( '' === $id || ! is_array( $job ) || empty( $job['recipients'] ) ) {
			return;
		}
		$lock = $key . '_lock';
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			if ( (int) get_option( $lock ) > time() - 5 * MINUTE_IN_SECONDS ) {
				return;
			}
			update_option( $lock, time(), false );
		}

		$size  = max( 1, (int) apply_filters( 'pue_background_batch_size', 20 ) );
		$batch = array_slice( $job['recipients'], (int) $job['offset'], $size );
		foreach ( $batch as $rcpt ) {
			if ( self::send_one( $rcpt, $job['subject'], $job['body'], $job['headers'], isset( $job['alt'] ) ? $job['alt'] : null ) ) {
				++$job['sent_count'];
				continue;
			}
			++$job['failed'];
			$error = PUE_Logger::instance()->take_last_error();
			if ( $error ) {
				$job['error']      = $error['error'];
				$job['error_code'] = $error['code'];
			}
		}
		$job['offset'] += count( $batch );
		$total          = count( $job['recipients'] );

		if ( $job['offset'] < $total ) {
			update_option( $key, $job, false );
			PUE_Logger::instance()->update_job_entry(
				$id,
				array(
					'sent_count' => (int) $job['sent_count'],
					'total'      => $total,
				)
			);
			wp_schedule_single_event( time() + 10, self::HOOK, array( $id ) );
			delete_option( $lock );
			return;
		}

		$sent = 0 === (int) $job['failed'];
		PUE_Logger::instance()->update_job_entry(
			$id,
			array(
				'sent'       => $sent ? 1 : 0,
				'mode'       => 'background',
				'sent_count' => (int) $job['sent_count'],
				'total'      => $total,
				'error'      => (string) $job['error'],
				'error_code' => (string) $job['error_code'],
			)
		);
		$post_id = (int) $job['post_id'];
		$editor  = ! empty( $job['editor_id'] ) && function_exists( 'get_userdata' ) ? get_userdata( (int) $job['editor_id'] ) : null;
		do_action( 'pue_email_sent', $sent, $job['recipients'], $job['subject'], $job['body'], $post_id, $post_id ? get_post( $post_id ) : null, null, $editor );
		delete_option( $key );
		delete_option( $lock );
	}
}
