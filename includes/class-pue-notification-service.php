<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUE_Notification_Service' ) ) {
	/** Decides whether a saved post notifies, then composes and delivers. */
	class PUE_Notification_Service {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Handle a saved post (wp_after_insert_post).
		 *
		 * @param int          $post_ID Post ID.
		 * @param WP_Post      $post_after Saved post.
		 * @param WP_Post|null $post_before Post before the save (null when unavailable).
		 * @return int|null
		 */
		public function handle_post_update( $post_ID, $post_after, $post_before ) {
			return $this->process( (int) $post_ID, $post_after, $post_before, false );
		}

		/**
		 * Send a notification for a published post now, ignoring the per-post flag, policy and cooldown.
		 *
		 * @param int $post_ID Post ID.
		 * @return int|null
		 */
		public function send_for_post( $post_ID ) {
			$post = get_post( (int) $post_ID );
			return $post ? $this->process( (int) $post_ID, $post, null, true ) : null;
		}

		private function process( $post_ID, $post_after, $post_before, $force ) {
			if ( ! $post_after || wp_is_post_autosave( $post_ID ) || wp_is_post_revision( $post_ID ) ) {
				return null;
			}
			if ( 'publish' !== $post_after->post_status ) {
				return null;
			}
			$event = ( $post_before && isset( $post_before->post_status ) && 'publish' !== $post_before->post_status ) ? 'publish' : 'update';
			if ( ! $force && 'publish' === $event && 'updates' === PUE_Defaults::option( 'pue_notify_events' ) ) {
				return null;
			}
			if ( ! $force && ! apply_filters( 'pue_should_notify', true, $post_ID, $post_after, $post_before ) ) {
				pue_consume_opt_in( $post_ID );
				return null;
			}
			// Reaching this point under the skip policy means an editor opted in, so record why nothing was sent.
			$explicit = $force || 'skip' === pue_send_policy();

			$allowed_types = (array) PUE_Defaults::option( 'pue_post_types' );
			if ( ! empty( $allowed_types ) && ! in_array( $post_after->post_type, $allowed_types, true ) ) {
				return $this->not_sent( 'post_type', $post_ID, $post_after, $event, $explicit );
			}
			$selected_roles = (array) PUE_Defaults::option( 'pue_roles' );
			if ( empty( $selected_roles ) ) {
				return $this->not_sent( 'no_roles', $post_ID, $post_after, $event, $explicit );
			}
			$cooldown = (int) PUE_Defaults::option( 'pue_cooldown_minutes' );
			if ( ! $force && $cooldown > 0 ) {
				$last = (int) get_post_meta( $post_ID, '_pue_last_sent', true );
				if ( $last && ( time() - $last ) < $cooldown * MINUTE_IN_SECONDS ) {
					return $this->not_sent( 'cooldown', $post_ID, $post_after, $event, $explicit );
				}
			}

			$editor = wp_get_current_user();
			$maps   = pue_build_placeholder_maps_update( $post_ID, $post_after, $post_before, $editor );
			$maps   = apply_filters( 'pue_placeholders', $maps, $post_ID, $post_after, $post_before, $editor );

			list($subject, $body_html, $headers) = pue_compose_email(
				'update',
				PUE_Defaults::option( 'pue_subject' ),
				PUE_Defaults::option( 'pue_message' ),
				$maps,
				array(
					'post_id'     => $post_ID,
					'post_after'  => $post_after,
					'post_before' => $post_before,
				),
				$editor
			);
			$alt                                 = PUE_Email_Composer::instance()->last_plaintext();

			$exclude    = (int) PUE_Defaults::option( 'pue_exclude_updater' ) && ! empty( $editor->user_email ) ? $editor->user_email : '';
			$recipients = pue_get_recipients( $selected_roles, $exclude );
			$recipients = apply_filters( 'pue_email_recipients', $recipients, $post_ID, $post_after, $post_before, $editor );
			$recipients = pue_sanitize_emails( (array) $recipients );
			if ( empty( $recipients ) ) {
				return $this->not_sent( 'no_recipients', $post_ID, $post_after, $event, $explicit );
			}

			// Consume the opt-in before sending so a quick second save cannot repeat it.
			pue_consume_opt_in( $post_ID );
			update_post_meta( $post_ID, '_pue_last_sent', time() );

			$extra = array(
				'post_title' => $post_after->post_title,
				'post_type'  => $post_after->post_type,
				'editor_id'  => isset( $editor->ID ) ? (int) $editor->ID : 0,
				'event'      => $event,
				'total'      => count( $recipients ),
			);

			if ( PUE_Delivery::should_queue( count( $recipients ) ) ) {
				$job_id = PUE_Delivery::queue(
					array(
						'post_id'    => $post_ID,
						'editor_id'  => $extra['editor_id'],
						'subject'    => $subject,
						'body'       => $body_html,
						'headers'    => $headers,
						'alt'        => $alt,
						'recipients' => $recipients,
					)
				);
				if ( $job_id ) {
					pue_log_event(
						'update',
						false,
						$recipients,
						$subject,
						$post_ID,
						array_merge(
							$extra,
							array(
								'mode'       => 'queued',
								'job_id'     => $job_id,
								'sent_count' => 0,
							)
						)
					);
					return $post_ID;
				}
			}

			$result = PUE_Delivery::send_now( $recipients, $subject, $body_html, $headers, $alt );
			pue_log_event(
				'update',
				$result['sent'],
				$recipients,
				$subject,
				$post_ID,
				array_merge(
					$extra,
					array(
						'mode'       => 'individual',
						'sent_count' => $result['sent_count'],
						'error'      => $result['error'],
						'error_code' => $result['error_code'],
					)
				)
			);
			do_action( 'pue_email_sent', $result['sent'], $recipients, $subject, $body_html, $post_ID, $post_after, $post_before, $editor );
			return $post_ID;
		}

		/**
		 * Consume the per-post choice and, when an editor opted in, log why nothing was sent.
		 */
		private function not_sent( $reason, $post_ID, $post_after, $event, $explicit ) {
			pue_consume_opt_in( $post_ID );
			if ( $explicit ) {
				$editor = wp_get_current_user();
				pue_log_event(
					'update',
					false,
					array(),
					'',
					$post_ID,
					array(
						'post_title' => $post_after->post_title,
						'post_type'  => $post_after->post_type,
						'editor_id'  => isset( $editor->ID ) ? (int) $editor->ID : 0,
						'mode'       => 'skipped',
						'reason'     => $reason,
						'event'      => $event,
						'sent_count' => 0,
						'total'      => 0,
					)
				);
			}
			return $post_ID;
		}

		public function send_test_email( $to, $subject, $message, $log = true ) {
			$to = sanitize_email( $to );
			if ( ! is_email( $to ) ) {
				return false;
			}
			$user                                = wp_get_current_user();
			$maps                                = pue_build_placeholder_maps_test( $user );
			list($subject, $body_html, $headers) = pue_compose_email( 'test', $subject, $message, $maps, array( 'post_id' => 0 ), $user );
			$sent                                = PUE_Delivery::send_one( $to, $subject, $body_html, $headers, PUE_Email_Composer::instance()->last_plaintext() );
			if ( $log ) {
				$error = $sent ? null : PUE_Logger::instance()->take_last_error();
				pue_log_event(
					'test',
					$sent,
					array( $to ),
					$subject,
					0,
					array(
						'error'      => $error ? $error['error'] : '',
						'error_code' => $error ? $error['code'] : '',
					)
				);
			}
			return $sent;
		}
	}
}
