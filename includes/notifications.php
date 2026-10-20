<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 4. Send Email on Post Update ----------
// Use wp_after_insert_post so that Classic/Block meta is already saved (e.g., our skip checkbox),
// preventing a race where post_updated fires before meta is persisted.
// Brand-new inserts ($update false) are ignored: programmatic and imported posts never notify.
function pue_notify_after_insert( $post_ID, $post, $update, $post_before = null ) {
	if ( ! $update ) {
		return;
	}
	return PUE_Notification_Service::instance()->handle_post_update( $post_ID, $post, $post_before instanceof WP_Post ? $post_before : null );
}
add_action( 'wp_after_insert_post', 'pue_notify_after_insert', 10, 4 );
