<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 7. Sanitize Callbacks ----------
function pue_sanitize_roles( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_roles( $input );
	}
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

function pue_sanitize_subject( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_subject( $input );
	}
	if ( null === $input ) {
		return ''; }
	if ( ! is_scalar( $input ) ) {
		return ''; }
	return sanitize_text_field( (string) $input );
}

function pue_sanitize_message( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_message( $input );
	}
	if ( null === $input ) {
		return ''; }
	if ( ! is_string( $input ) ) {
		$input = (string) $input; }
	return wp_kses_post( $input );
}

function pue_sanitize_post_types( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_post_types( $input );
	}
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

function pue_sanitize_bool( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_bool( $input );
	}
	return $input ? 1 : 0;
}

function pue_sanitize_retention( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_retention( $input );
	}
	$allowed = array( 50, 200, 1000 );
	$val     = (int) $input;
	return in_array( $val, $allowed, true ) ? $val : 50;
}

function pue_sanitize_hex( $color ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_hex( $color );
	}
	$color = is_string( $color ) ? $color : '';
	$c     = sanitize_hex_color( $color );
	return $c ? $c : '#0073aa';
}

function pue_sanitize_email( $input ) {
	if ( class_exists( 'PUE_Settings_Manager' ) ) {
		return PUE_Settings_Manager::instance()->sanitize_email( $input );
	}
	if ( null === $input ) {
		return '';
	}
	if ( ! is_string( $input ) ) {
		$input = (string) $input;
	}
	return sanitize_email( $input );
}

// Delivery mode sanitizer removed in 1.3.0 (individual-only delivery)
