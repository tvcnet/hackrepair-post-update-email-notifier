<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUE_Logger' ) ) {
	/** Recent email log stored in the pue_logs option. */
	class PUE_Logger {
		private static $instance = null;

		/**
		 * Failure detail from the most recent plugin wp_mail() call.
		 *
		 * @var array|null
		 */
		private $last_error = null;

		/** Entry modes that are not delivery failures. */
		const NON_FAILURE_MODES = array( 'skipped', 'queued' );

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function is_enabled() {
			return (int) get_option( 'pue_enable_logging', 0 ) === 1;
		}

		public function retention() {
			$retention = (int) get_option( 'pue_log_retention', 50 );
			return in_array( $retention, array( 50, 200, 1000 ), true ) ? $retention : 50;
		}

		public function log_event( $type, $sent, $recipients, $subject, $post_id = 0, $extra = array() ) {
			if ( ! $this->is_enabled() ) {
				return;
			}
			$entry = array(
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
				'ts'         => current_time( 'timestamp' ),
				'type'       => (string) $type,
				'sent'       => $sent ? 1 : 0,
				'recipients' => is_array( $recipients ) ? array_values( $recipients ) : array( (string) $recipients ),
				'subject'    => (string) $subject,
				'post_id'    => (int) $post_id,
			);
			foreach ( array( 'post_title', 'post_type', 'editor_id', 'error', 'error_code', 'mode', 'sent_count', 'total', 'reason', 'job_id', 'event' ) as $k ) {
				if ( isset( $extra[ $k ] ) && '' !== $extra[ $k ] ) {
					$entry[ $k ] = $extra[ $k ]; }
			}
			$logs = get_option( 'pue_logs', array() );
			if ( ! is_array( $logs ) ) {
				$logs = array(); }
			$logs[]    = $entry;
			$retention = $this->retention();
			if ( count( $logs ) > $retention ) {
				$logs = array_slice( $logs, -$retention );
			}
			update_option( 'pue_logs', $logs, false );
		}

		/**
		 * Merge fields into the entry created for a background job.
		 *
		 * @param string $job_id Job id.
		 * @param array  $fields Fields to merge.
		 */
		public function update_job_entry( $job_id, $fields ) {
			$logs = get_option( 'pue_logs', array() );
			if ( ! is_array( $logs ) ) {
				return;
			}
			for ( $i = count( $logs ) - 1; $i >= 0; $i-- ) {
				if ( isset( $logs[ $i ]['job_id'] ) && $logs[ $i ]['job_id'] === $job_id ) {
					$fields     = array_filter(
						$fields,
						static function ( $v ) {
							return '' !== $v;
						}
					);
					$logs[ $i ] = array_merge( $logs[ $i ], $fields );
					update_option( 'pue_logs', $logs, false );
					return;
				}
			}
		}

		/**
		 * Whether an entry represents a delivery failure (not a skip or a queued job).
		 *
		 * @param array $entry Log entry.
		 * @return bool
		 */
		public function is_failure( $entry ) {
			if ( ! is_array( $entry ) || ! empty( $entry['sent'] ) ) {
				return false;
			}
			$mode = isset( $entry['mode'] ) ? (string) $entry['mode'] : '';
			return ! in_array( $mode, self::NON_FAILURE_MODES, true );
		}

		/**
		 * Count delivery failures since a site-local timestamp.
		 *
		 * @param int $since Site-local timestamp.
		 * @return int
		 */
		public function count_failures_since( $since ) {
			$count = 0;
			foreach ( (array) get_option( 'pue_logs', array() ) as $entry ) {
				if ( $this->is_failure( $entry ) && isset( $entry['ts'] ) && (int) $entry['ts'] >= $since ) {
					++$count;
				}
			}
			return $count;
		}

		public function reset_last_error() {
			$this->last_error = null;
		}

		/**
		 * Return and clear the most recent captured failure.
		 *
		 * @return array|null {error: string, code: string}
		 */
		public function take_last_error() {
			$error            = $this->last_error;
			$this->last_error = null;
			return $error;
		}

		/**
		 * Capture wp_mail_failed details for this plugin's emails (identified by the X-PUE header).
		 * The detail is attached to the sending flow's summary entry rather than logged separately.
		 */
		public function capture_mail_failure( $wp_error ) {
			if ( ! is_wp_error( $wp_error ) ) {
				return;
			}
			$data = $wp_error->get_error_data();
			if ( ! is_array( $data ) ) {
				return;
			}
			$headers      = isset( $data['headers'] ) ? $data['headers'] : array();
			$header_lines = is_string( $headers )
				? preg_split( '/\r\n|\r|\n/', $headers )
				: ( is_array( $headers ) ? $headers : array() );
			$is_plugin    = false;
			foreach ( $header_lines as $name => $line ) {
				// WordPress passes parsed custom headers as name => value pairs.
				if ( ( is_string( $name ) && 0 === strcasecmp( $name, 'X-PUE' ) ) || ( is_string( $line ) && 0 === stripos( $line, 'X-PUE:' ) ) ) {
					$is_plugin = true;
					break;
				}
			}
			if ( ! $is_plugin ) {
				return;
			}
			$this->last_error = array(
				'error' => (string) $wp_error->get_error_message(),
				'code'  => (string) $wp_error->get_error_code(),
			);
		}

		/**
		 * Write the log as CSV to an open stream.
		 *
		 * @param resource $out Stream.
		 */
		public function write_csv( $out ) {
			fputcsv( $out, array( 'time', 'type', 'status', 'post_id', 'post_title', 'post_type', 'editor_id', 'recipients', 'subject', 'reason', 'error' ), ',', '"', '' );
			foreach ( (array) get_option( 'pue_logs', array() ) as $entry ) {
				$time   = isset( $entry['ts'] ) ? (int) $entry['ts'] : time();
				$recips = isset( $entry['recipients'] ) && is_array( $entry['recipients'] ) ? implode( '; ', array_map( array( $this, 'csv_safe' ), $entry['recipients'] ) ) : '';
				fputcsv(
					$out,
					array(
						date_i18n( 'Y-m-d H:i:s', $time ),
						isset( $entry['type'] ) ? (string) $entry['type'] : 'update',
						$this->status( $entry ),
						isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0,
						$this->csv_safe( isset( $entry['post_title'] ) ? (string) $entry['post_title'] : '' ),
						$this->csv_safe( isset( $entry['post_type'] ) ? (string) $entry['post_type'] : '' ),
						isset( $entry['editor_id'] ) ? (int) $entry['editor_id'] : 0,
						$recips,
						$this->csv_safe( isset( $entry['subject'] ) ? (string) $entry['subject'] : '' ),
						$this->csv_safe( isset( $entry['reason'] ) ? (string) $entry['reason'] : '' ),
						$this->csv_safe( isset( $entry['error'] ) ? (string) $entry['error'] : '' ),
					),
					',',
					'"',
					''
				);
			}
		}

		/**
		 * Machine status for an entry.
		 *
		 * @param array $entry Entry.
		 * @return string sent|skipped|queued|partial|failed
		 */
		public function status( $entry ) {
			if ( ! empty( $entry['sent'] ) ) {
				return 'sent';
			}
			$mode = isset( $entry['mode'] ) ? (string) $entry['mode'] : '';
			if ( 'skipped' === $mode || 'queued' === $mode ) {
				return $mode;
			}
			$sc  = isset( $entry['sent_count'] ) ? (int) $entry['sent_count'] : 0;
			$tot = isset( $entry['total'] ) ? (int) $entry['total'] : 0;
			return ( $sc > 0 && $sc < $tot ) ? 'partial' : 'failed';
		}

		public function export_csv_and_exit() {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=post-update-email-notifier-log-' . gmdate( 'Ymd-His' ) . '.csv' );
			$out = fopen( 'php://output', 'w' );
			$this->write_csv( $out );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the CSV response stream, not a filesystem file.
			fclose( $out );
			exit;
		}

		public function csv_safe( $value ) {
			if ( ! is_string( $value ) ) {
				return $value; }
			$trim = ltrim( $value );
			if ( '' !== $trim && in_array( $trim[0], array( '=', '+', '-', '@' ), true ) ) {
				return "'" . $value;
			}
			return $value;
		}

		/**
		 * Personal data exporter: log entries that list this address.
		 *
		 * @param string $email Address.
		 * @return array
		 */
		public function privacy_export( $email ) {
			$items = array();
			foreach ( (array) get_option( 'pue_logs', array() ) as $i => $entry ) {
				if ( empty( $entry['recipients'] ) || ! in_array( strtolower( $email ), array_map( 'strtolower', (array) $entry['recipients'] ), true ) ) {
					continue;
				}
				$items[] = array(
					'group_id'    => 'pue-email-log',
					'group_label' => __( 'Post update emails', 'hackrepair-post-update-email-notifier' ),
					'item_id'     => 'pue-log-' . (int) $i,
					'data'        => array(
						array(
							'name'  => __( 'Time', 'hackrepair-post-update-email-notifier' ),
							'value' => date_i18n( 'Y-m-d H:i:s', isset( $entry['ts'] ) ? (int) $entry['ts'] : 0 ),
						),
						array(
							'name'  => __( 'Subject', 'hackrepair-post-update-email-notifier' ),
							'value' => isset( $entry['subject'] ) ? (string) $entry['subject'] : '',
						),
						array(
							'name'  => __( 'Status', 'hackrepair-post-update-email-notifier' ),
							'value' => $this->status( $entry ),
						),
					),
				);
			}
			return array(
				'data' => $items,
				'done' => true,
			);
		}

		/**
		 * Personal data eraser: remove this address from log entries.
		 *
		 * @param string $email Address.
		 * @return array
		 */
		public function privacy_erase( $email ) {
			$logs    = get_option( 'pue_logs', array() );
			$removed = false;
			if ( is_array( $logs ) ) {
				foreach ( $logs as $i => $entry ) {
					if ( empty( $entry['recipients'] ) || ! is_array( $entry['recipients'] ) ) {
						continue;
					}
					$kept = array_values(
						array_filter(
							$entry['recipients'],
							static function ( $addr ) use ( $email ) {
								return strtolower( (string) $addr ) !== strtolower( $email );
							}
						)
					);
					if ( count( $kept ) !== count( $entry['recipients'] ) ) {
						$logs[ $i ]['recipients'] = $kept;
						$removed                  = true;
					}
				}
				if ( $removed ) {
					update_option( 'pue_logs', $logs, false );
				}
			}
			return array(
				'items_removed'  => $removed,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
	}
}
