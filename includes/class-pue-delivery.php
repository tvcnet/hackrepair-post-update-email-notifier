<?php
/**
 * Message delivery: immediate sends and WP-Cron background batches.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** Sends one message per recipient, either now or in background batches. */
final class PUE_Delivery {

	const HOOK          = 'pue_send_batch';
	const JOB_PREFIX    = 'pue_job_';
	const RECOVERY_HOOK = 'pue_recover_jobs';

	/**
	 * Plaintext alternative for the message currently being sent.
	 *
	 * @var string|null
	 */
	private static $pending_alt = null;

	/** Register delivery hooks. */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_job' ), 10, 1 );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::RECOVERY_HOOK, array( __CLASS__, 'recover_jobs' ) );
		add_action( 'init', array( __CLASS__, 'ensure_recovery' ) );
		register_deactivation_hook( PUE_MAIN_FILE, array( __CLASS__, 'deactivate' ) );
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

	/** Queue a message. Recovery remains independent of the optional email log. */
	public static function queue( $job ) {
		$id  = strtolower( wp_generate_password( 12, false, false ) );
		$job = array_merge(
			$job,
			array(
				'id'                => $id,
				'offset'            => 0,
				'sent_count'        => 0,
				'failed'            => 0,
				'uncertain'         => 0,
				'error'             => '',
				'error_code'        => '',
				'created'           => time(),
				'state'             => 'queued',
				'schedule_failures' => 0,
			)
		);
		if ( ! add_option( self::JOB_PREFIX . $id, $job, '', 'no' ) ) {
			return false;
		}
		self::ensure_recovery();
		self::schedule_job( $job, time() );
		return $id;
	}

	/** Register a periodic recovery callback, also retried on later requests if scheduling fails. */
	public static function ensure_recovery() {
		if ( ! wp_next_scheduled( self::RECOVERY_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'pue_fifteen_minutes', self::RECOVERY_HOOK );
		}
	}

	/**
	 * Recovery interval.
	 *
	 * @param array $schedules WordPress schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['pue_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every fifteen minutes (post update emails)', 'hackrepair-post-update-email-notifier' ),
		);
		return $schedules;
	}

	/** Pending and failed jobs, independent of log retention or logging being enabled.
	 * @return array
	 */
	public static function jobs() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Enumerate only this plugin's non-autoloaded queue; values use the option API.
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::JOB_PREFIX ) . '%' ) );
		$jobs = array();
		foreach ( $keys as $key ) {
			$job = get_option( $key );
			if ( is_array( $job ) && isset( $job['id'], $job['recipients'], $job['offset'] ) ) {
				$jobs[ $job['id'] ] = $job;
			}
		}
		return $jobs;
	}

	/** Retry missing callbacks without executing mail in a page request. */
	public static function recover_jobs() {
		foreach ( self::jobs() as $job ) {
			$id = $job['id'];
			if ( (int) $job['created'] < time() - 7 * DAY_IN_SECONDS ) {
				$owner = self::acquire_lock( self::JOB_PREFIX . $job['id'] . '_lock' );
				if ( false !== $owner ) {
					try {
						$job = self::load_job( $job['id'] );
						if ( is_array( $job ) ) {
							$job['error']      = __( 'Background message expired after seven days. Remaining recipients were not sent.', 'hackrepair-post-update-email-notifier' );
							$job['error_code'] = 'pue_job_expired';
							self::finish_job( $job, false, $owner );
						}
					} finally {
						self::release_lock( self::JOB_PREFIX . $id . '_lock', $owner );
					}
				}
				continue;
			}
			if ( 'failed' === ( $job['state'] ?? '' ) || wp_next_scheduled( self::HOOK, array( $job['id'] ) ) ) {
				continue;
			}
			$lock  = self::JOB_PREFIX . $job['id'] . '_lock';
			$owner = self::acquire_lock( $lock );
			if ( false === $owner ) {
				continue;
			}
			try {
				$job = self::load_job( $job['id'] );
				if ( is_array( $job ) && 'failed' !== ( $job['state'] ?? '' ) ) {
					self::schedule_job( $job, time() + 10, $owner );
				}
			} finally {
				self::release_lock( $lock, $owner );
			}
		}
	}

	/**
	 * Schedule or record a recoverable failure; stop after five consecutive scheduling failures.
	 *
	 * @param array $job Persisted job, updated by reference.
	 * @param int $when Unix timestamp.
	 */
	private static function schedule_job( &$job, $when, $owner = null ) {
		$result = wp_schedule_single_event( $when, self::HOOK, array( $job['id'] ), true );
		if ( true === $result || wp_next_scheduled( self::HOOK, array( $job['id'] ) ) ) {
			$job['state']             = 'queued';
			$job['schedule_failures'] = 0;
			if ( 'pue_schedule_failed' === ( $job['error_code'] ?? '' ) ) {
				$job['error_code'] = '';
				$job['error']      = '';
			}
		} else {
			$job['schedule_failures'] = (int) ( $job['schedule_failures'] ?? 0 ) + 1;
			$job['state']             = $job['schedule_failures'] >= 5 ? 'failed' : 'paused';
			if ( empty( $job['error_code'] ) || 'pue_schedule_failed' === $job['error_code'] ) {
				$job['error_code'] = 'pue_schedule_failed';
				$job['error']      = __( 'Background scheduling failed. Recovery will retry up to five times; check Site Health or wp pue jobs.', 'hackrepair-post-update-email-notifier' );
			}
		}
		if ( null !== $owner ) {
			if ( ! self::store_job( $job, $owner ) ) {
				return;
			}
		} else {
			update_option( self::JOB_PREFIX . $job['id'], $job, false );
		}
		self::log_progress( $job );
	}

	/**
	 * Atomically remove only the observed lock (never a replacement worker's lock).
	 *
	 * @param string $key Lock option.
	 * @param mixed $value Observed lock value.
	 * @return bool
	 */
	private static function release_lock( $key, $value ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete is required for atomic lock ownership; clear the option caches below.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $value ) ) );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $deleted;
	}

	/**
	 * Acquire a lease, using compare-and-delete for expired leases from earlier workers.
	 *
	 * @param string $key Lock option.
	 * @return array|false
	 */
	private static function acquire_lock( $key ) {
		$value = array(
			'token'   => wp_generate_password( 24, false, false ),
			'expires' => time() + 5 * MINUTE_IN_SECONDS,
		);
		if ( add_option( $key, $value, '', 'no' ) ) {
			return $value;
		}
		$old    = get_option( $key );
		$expiry = is_array( $old ) ? (int) ( $old['expires'] ?? 0 ) : (int) $old + 5 * MINUTE_IN_SECONDS;
		if ( $expiry >= time() || ! self::release_lock( $key, $old ) ) {
			return false;
		}
		return add_option( $key, $value, '', 'no' ) ? $value : false;
	}

	/**
	 * Persist cursor before transport; an interrupted recipient is never retried automatically.
	 *
	 * @param string $id Job id.
	 */
	public static function run_job( $id ) {
		$id = (string) $id;
		if ( '' === $id || ! preg_match( '/^[a-z0-9]+$/', $id ) ) {
			return;
		}
		$key   = self::JOB_PREFIX . $id;
		$lock  = $key . '_lock';
		$owner = self::acquire_lock( $lock );
		if ( false === $owner ) {
			return;
		}
		try {
			// The job may have advanced or completed before this worker acquired its lock.
			$job = self::load_job( $id );
			if ( ! is_array( $job ) || empty( $job['recipients'] ) || 'failed' === ( $job['state'] ?? '' ) ) {
				return;
			}
			if ( isset( $job['inflight'] ) ) {
				++$job['failed'];
				$job['uncertain']  = (int) ( $job['uncertain'] ?? 0 ) + 1;
				$job['error_code'] = 'pue_delivery_uncertain';
				$job['error']      = __( 'A send was interrupted; its outcome is unknown and it will not be resent automatically.', 'hackrepair-post-update-email-notifier' );
				unset( $job['inflight'] );
			}
			if ( (int) $job['created'] < time() - 7 * DAY_IN_SECONDS ) {
				$job['error_code'] = 'pue_job_expired';
				$job['error']      = __( 'Background message expired after seven days.', 'hackrepair-post-update-email-notifier' );
				self::finish_job( $job, false, $owner );
				return;
			}
			$size  = max( 1, min( 100, (int) apply_filters( 'pue_background_batch_size', 20 ) ) );
			$total = count( $job['recipients'] );
			$end   = min( $total, (int) $job['offset'] + $size );
			while ( (int) $job['offset'] < $end ) {
				if ( get_option( $lock ) !== $owner ) {
					return;
				}
				$index           = (int) $job['offset'];
				$job['inflight'] = $index;
				$job['offset']   = $index + 1;
				if ( ! self::store_job( $job, $owner ) ) {
					return; // Never send if the checkpoint could not be saved.
				}
				try {
					$ok = self::send_one( $job['recipients'][ $index ], $job['subject'], $job['body'], $job['headers'], $job['alt'] ?? null );
				} catch ( \Throwable $e ) {
					$ok                = false;
					$job['uncertain']  = (int) ( $job['uncertain'] ?? 0 ) + 1;
					$job['error_code'] = 'pue_transport_exception';
					$job['error']      = __( 'The mail transport raised an exception; that recipient will not be resent automatically.', 'hackrepair-post-update-email-notifier' );
				}
				if ( get_option( $lock ) !== $owner ) {
					return; // A recovery worker owns the newer state; do not overwrite it.
				}
				if ( $ok ) {
					++$job['sent_count'];
				} else {
					++$job['failed'];
					$error = PUE_Logger::instance()->take_last_error();
					if ( $error ) {
						$job['error']      = $error['error'];
						$job['error_code'] = $error['code'];
					}
				}
				unset( $job['inflight'] );
				if ( ! self::store_job( $job, $owner ) ) {
					return;
				}
			}
			if ( (int) $job['offset'] < $total ) {
				self::schedule_job( $job, time() + 10, $owner );
			} else {
				self::finish_job( $job, 0 === (int) $job['failed'], $owner );
			}
		} finally {
			self::release_lock( $lock, $owner );
		}
	}

	/**
	 * Invalidate any snapshot cached before lease acquisition, then read current state.
	 *
	 * @param string $id Job id.
	 * @return mixed
	 */
	private static function load_job( $id ) {
		$key = self::JOB_PREFIX . $id;
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return get_option( $key );
	}

	/**
	 * Fence every worker write with the lock value in the same SQL statement.
	 *
	 * @param array $job Job state.
	 * @param array $owner Lease token.
	 * @param bool $delete Remove the completed job.
	 * @return bool
	 */
	private static function store_job( &$job, $owner, $delete = false ) {
		global $wpdb;
		$key                = self::JOB_PREFIX . $job['id'];
		$job['write_token'] = wp_generate_password( 24, false, false );
		if ( $delete ) {
			$sql = $wpdb->prepare( "DELETE job FROM {$wpdb->options} AS job INNER JOIN {$wpdb->options} AS lease ON lease.option_name = %s AND lease.option_value = %s WHERE job.option_name = %s", $key . '_lock', maybe_serialize( $owner ), $key );
		} else {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->options} AS job INNER JOIN {$wpdb->options} AS lease ON lease.option_name = %s AND lease.option_value = %s SET job.option_value = %s WHERE job.option_name = %s", $key . '_lock', maybe_serialize( $owner ), maybe_serialize( $job ), $key );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; atomic lease fencing cannot be expressed with update_option(). Clear this option's cache afterwards.
		$result = $wpdb->query( $sql );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}

	/**
	 * Explicitly retry only unattempted recipients after the operator checks the failure.
	 *
	 * @param string $id Job id.
	 * @return bool
	 */
	public static function retry_job( $id ) {
		if ( ! preg_match( '/^[a-z0-9]+$/', (string) $id ) ) {
			return false;
		}
		$lock  = self::JOB_PREFIX . $id . '_lock';
		$owner = self::acquire_lock( $lock );
		if ( false === $owner ) {
			return false;
		}
		try {
			$job = self::load_job( $id );
			if ( ! is_array( $job ) || (int) $job['created'] < time() - 7 * DAY_IN_SECONDS ) {
				return false;
			}
			$job['schedule_failures'] = 0;
			self::schedule_job( $job, time() + 10, $owner );
			return 'queued' === $job['state'];
		} finally {
			self::release_lock( $lock, $owner );
		}
	}

	/**
	 * Update the optional log without relying on it for recovery.
	 *
	 * @param array $job Job state.
	 */
	private static function log_progress( $job ) {
		PUE_Logger::instance()->update_job_entry(
			$job['id'],
			array(
				'mode'       => 'failed' === ( $job['state'] ?? '' ) ? 'background' : 'queued',
				'sent_count' => (int) $job['sent_count'],
				'total'      => count( $job['recipients'] ),
				'error'      => $job['error'],
				'error_code' => $job['error_code'],
			)
		);
	}

	/**
	 * Complete the log and remove sensitive queued payload before calling external hooks.
	 *
	 * @param array $job Job.
	 * @param bool $sent All recipients accepted.
	 */
	private static function finish_job( $job, $sent, $owner ) {
		if ( ! self::store_job( $job, $owner, true ) ) {
			return;
		}
		PUE_Logger::instance()->update_job_entry(
			$job['id'],
			array(
				'sent'       => $sent ? 1 : 0,
				'mode'       => 'background',
				'sent_count' => (int) $job['sent_count'],
				'total'      => count( $job['recipients'] ),
				'error'      => $job['error'],
				'error_code' => $job['error_code'],
			)
		);
		wp_clear_scheduled_hook( self::HOOK, array( $job['id'] ) );
		$post_id = (int) $job['post_id'];
		$editor  = ! empty( $job['editor_id'] ) ? get_userdata( (int) $job['editor_id'] ) : null;
		do_action( 'pue_email_sent', $sent, $job['recipients'], $job['subject'], $job['body'], $post_id, $post_id ? get_post( $post_id ) : null, null, $editor );
	}

	/** Remove callbacks while inactive; queued payload resumes after reactivation, within seven days. */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::RECOVERY_HOOK );
		wp_unschedule_hook( self::HOOK );
	}
}
