<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 7. Sanitize Callbacks ----------
function pue_sanitize_roles( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_roles( $input );
}

function pue_sanitize_subject( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_subject( $input );
}

function pue_sanitize_message( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_message( $input );
}

function pue_sanitize_post_types( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_post_types( $input );
}

function pue_sanitize_bool( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_bool( $input );
}

function pue_sanitize_retention( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_retention( $input );
}

function pue_sanitize_hex( $color ) {
	return PUE_Settings_Manager::instance()->sanitize_hex( $color );
}

function pue_sanitize_email( $input ) {
	return PUE_Settings_Manager::instance()->sanitize_email( $input );
}

// Delivery mode sanitizer removed in 1.3.0 (individual-only delivery)
