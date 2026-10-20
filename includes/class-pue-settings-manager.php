<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUE_Settings_Manager' ) ) {
	class PUE_Settings_Manager {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Option => [group, type, sanitize callback].
		 *
		 * @return array
		 */
		public function schema() {
			return array(
				'pue_roles'            => array( 'pue_group_notifications', 'array', 'pue_sanitize_roles' ),
				'pue_subject'          => array( 'pue_group_notifications', 'string', 'pue_sanitize_subject' ),
				'pue_message'          => array( 'pue_group_notifications', 'string', 'pue_sanitize_message' ),
				'pue_post_types'       => array( 'pue_group_notifications', 'array', 'pue_sanitize_post_types' ),
				'pue_exclude_updater'  => array( 'pue_group_notifications', 'boolean', 'pue_sanitize_bool' ),
				'pue_enable_logging'   => array( 'pue_group_notifications', 'boolean', 'pue_sanitize_bool' ),
				'pue_log_retention'    => array( 'pue_group_notifications', 'integer', 'pue_sanitize_retention' ),
				'pue_send_policy'      => array( 'pue_group_notifications', 'string', array( $this, 'sanitize_send_policy' ) ),
				'pue_notify_events'    => array( 'pue_group_notifications', 'string', array( $this, 'sanitize_notify_events' ) ),
				'pue_cooldown_minutes' => array( 'pue_group_notifications', 'integer', array( $this, 'sanitize_cooldown' ) ),
				'pue_delivery_mode'    => array( 'pue_group_notifications', 'string', array( $this, 'sanitize_delivery_mode' ) ),
				'pue_from_name'        => array( 'pue_group_identity', 'string', 'pue_sanitize_subject' ),
				'pue_from_email'       => array( 'pue_group_identity', 'string', 'pue_sanitize_email' ),
				'pue_reply_to_email'   => array( 'pue_group_identity', 'string', 'pue_sanitize_email' ),
				'pue_header_text'      => array( 'pue_group_branding', 'string', 'pue_sanitize_subject' ),
				'pue_header_bg_color'  => array( 'pue_group_branding', 'string', 'pue_sanitize_hex' ),
				'pue_footer_text'      => array( 'pue_group_branding', 'string', 'pue_sanitize_subject' ),
			);
		}

		public function register_settings() {
			foreach ( $this->schema() as $option => $def ) {
				register_setting(
					$def[0],
					$option,
					array(
						'type'              => $def[1],
						'sanitize_callback' => $def[2],
						'default'           => PUE_Defaults::get( $option ),
					)
				);
			}
		}

		// Sanitizers
		public function sanitize_roles( $input ) {
			$allowed   = array_keys( wp_roles()->roles );
			$sanitized = array();
			if ( is_array( $input ) ) {
				foreach ( $input as $role ) {
					$role = sanitize_key( $role );
					if ( in_array( $role, $allowed, true ) ) {
						$sanitized[] = $role;
					}
				}
			}
			return array_values( array_unique( $sanitized ) );
		}

		public function sanitize_subject( $input ) {
			if ( null === $input ) {
				return ''; }
			if ( ! is_scalar( $input ) ) {
				return ''; }
			return sanitize_text_field( (string) $input );
		}

		public function sanitize_message( $input ) {
			if ( null === $input ) {
				return ''; }
			if ( ! is_string( $input ) ) {
				$input = (string) $input; }
			return wp_kses_post( $input );
		}

		public function sanitize_post_types( $input ) {
			$allowed   = get_post_types( array(), 'names' );
			$sanitized = array();
			if ( is_array( $input ) ) {
				foreach ( $input as $pt ) {
					$pt = sanitize_key( $pt );
					if ( in_array( $pt, $allowed, true ) ) {
						$sanitized[] = $pt;
					}
				}
			}
			return array_values( array_unique( $sanitized ) );
		}

		public function sanitize_bool( $input ) {
			return $input ? 1 : 0;
		}

		public function sanitize_retention( $input ) {
			$allowed = array( 50, 200, 1000 );
			$val     = (int) $input;
			return in_array( $val, $allowed, true ) ? $val : 50;
		}

		public function sanitize_hex( $color ) {
			$color = is_string( $color ) ? $color : '';
			$c     = sanitize_hex_color( $color );
			return $c ? $c : '#0073aa';
		}

		public function sanitize_send_policy( $input ) {
			return 'send' === $input ? 'send' : 'skip';
		}

		public function sanitize_notify_events( $input ) {
			return 'updates' === $input ? 'updates' : 'all';
		}

		public function sanitize_cooldown( $input ) {
			$val = (int) $input;
			return in_array( $val, array( 0, 5, 15, 60 ), true ) ? $val : 0;
		}

		public function sanitize_delivery_mode( $input ) {
			return in_array( $input, array( 'auto', 'sync', 'background' ), true ) ? $input : 'auto';
		}

		public function sanitize_email( $input ) {
			if ( null === $input ) {
				return '';
			}
			if ( ! is_string( $input ) ) {
				$input = (string) $input;
			}
			return sanitize_email( $input );
		}
	}
}
